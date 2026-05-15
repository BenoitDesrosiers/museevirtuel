<?php

use App\Models\EtudiantReference;
use App\Models\EtudiantZoteroCredential;
use App\Models\User;
use Illuminate\Support\Facades\Http;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Crée un étudiant prêt à l'emploi pour les tests de références personnelles.
 */
function creerEtudiant(): User
{
    return User::factory()->create(['role' => 'etudiant']);
}

/**
 * Crée une référence manuelle appartenant à un étudiant.
 */
function creerReference(User $etudiant, array $overrides = []): EtudiantReference
{
    return EtudiantReference::create(array_merge([
        'user_id' => $etudiant->id,
        'titre' => 'Revue Historia',
        'url' => null,
        'ordre' => 1,
    ], $overrides));
}

/**
 * Enregistre des credentials Zotero (non chiffrés en test) pour un étudiant.
 */
function creerCredential(User $etudiant): EtudiantZoteroCredential
{
    return EtudiantZoteroCredential::create([
        'user_id' => $etudiant->id,
        'zotero_user_id' => '12345678',
        'api_key' => 'test-api-key',
    ]);
}

/**
 * Retourne une réponse Zotero simulée avec un item de type article de journal.
 *
 * @return array<int, array<string, mixed>>
 */
function fakeZoteroItems(): array
{
    return [
        [
            'key' => 'ABC12345',
            'data' => [
                'key' => 'ABC12345',
                'itemType' => 'journalArticle',
                'title' => 'L\'histoire du Québec',
                'creators' => [
                    ['creatorType' => 'author', 'firstName' => 'Marie', 'lastName' => 'Curie'],
                ],
                'date' => '2021',
                'publicationTitle' => 'Revue d\'histoire de l\'Amérique française',
                'url' => 'https://www.erudit.org/en/journals/haf/',
                'DOI' => '10.1234/haf.2021',
            ],
        ],
    ];
}

// ─── store() — ajout manuel ───────────────────────────────────────────────────

test("l'étudiant peut ajouter une référence manuelle avec titre et url", function () {
    $etudiant = creerEtudiant();

    $this->actingAs($etudiant)
        ->post('/etudiant/references', [
            'titre' => 'Revue d\'histoire de l\'Amérique française',
            'url' => 'https://www.erudit.org/en/journals/haf/',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('etudiant_references', [
        'user_id' => $etudiant->id,
        'titre' => 'Revue d\'histoire de l\'Amérique française',
        'url' => 'https://www.erudit.org/en/journals/haf/',
        'zotero_item_key' => null,
    ]);
});

test("l'étudiant peut ajouter une référence manuelle sans url", function () {
    $etudiant = creerEtudiant();

    $this->actingAs($etudiant)
        ->post('/etudiant/references', ['titre' => 'Revue Historia'])
        ->assertRedirect();

    $this->assertDatabaseHas('etudiant_references', [
        'user_id' => $etudiant->id,
        'titre' => 'Revue Historia',
    ]);
});

test('store rejette un titre vide', function () {
    $etudiant = creerEtudiant();

    $this->actingAs($etudiant)
        ->post('/etudiant/references', ['titre' => '', 'url' => null])
        ->assertSessionHasErrors('titre');
});

test('store rejette une url invalide', function () {
    $etudiant = creerEtudiant();

    $this->actingAs($etudiant)
        ->post('/etudiant/references', ['titre' => 'Revue', 'url' => 'pas-une-url'])
        ->assertSessionHasErrors('url');
});

test('un enseignant ne peut pas accéder aux routes de références étudiants', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    // Le middleware role:etudiant redirige l'enseignant
    $this->actingAs($enseignant)
        ->post('/etudiant/references', ['titre' => 'Test'])
        ->assertRedirect();

    $this->assertDatabaseEmpty('etudiant_references');
});

// ─── destroy() ────────────────────────────────────────────────────────────────

test("l'étudiant peut supprimer sa propre référence", function () {
    $etudiant = creerEtudiant();
    $reference = creerReference($etudiant);

    $this->actingAs($etudiant)
        ->delete("/etudiant/references/{$reference->id}")
        ->assertRedirect();

    $this->assertDatabaseMissing('etudiant_references', ['id' => $reference->id]);
});

test("l'étudiant ne peut pas supprimer la référence d'un autre étudiant", function () {
    $proprietaire = creerEtudiant();
    $intrus = creerEtudiant();
    $reference = creerReference($proprietaire);

    $this->actingAs($intrus)
        ->delete("/etudiant/references/{$reference->id}")
        ->assertForbidden();

    $this->assertDatabaseHas('etudiant_references', ['id' => $reference->id]);
});

// ─── syncZotero() ─────────────────────────────────────────────────────────────

test('sync importe les items depuis la bibliothèque Zotero et les stocke en base', function () {
    Http::preventStrayRequests();
    Http::fake(['api.zotero.org/*' => Http::response(fakeZoteroItems(), 200)]);

    $etudiant = creerEtudiant();
    creerCredential($etudiant);

    $this->actingAs($etudiant)
        ->post('/etudiant/references/sync')
        ->assertRedirect();

    $this->assertDatabaseHas('etudiant_references', [
        'user_id' => $etudiant->id,
        'zotero_item_key' => 'ABC12345',
        'titre' => 'L\'histoire du Québec',
        'doi' => '10.1234/haf.2021',
    ]);
});

test('une sync répétée ne crée pas de doublon (upsert)', function () {
    Http::preventStrayRequests();
    Http::fake(['api.zotero.org/*' => Http::response(fakeZoteroItems(), 200)]);

    $etudiant = creerEtudiant();
    creerCredential($etudiant);

    // Deux syncs successives
    $this->actingAs($etudiant)->post('/etudiant/references/sync');
    $this->actingAs($etudiant)->post('/etudiant/references/sync');

    expect(EtudiantReference::where('user_id', $etudiant->id)->count())->toBe(1);
});

test('sync sans credentials redirige avec une erreur', function () {
    $etudiant = creerEtudiant();

    $this->actingAs($etudiant)
        ->post('/etudiant/references/sync')
        ->assertRedirect();

    // Aucun appel HTTP ne doit avoir été fait
    Http::assertNothingSent();
});

test('sync avec clé révoquée (403 Zotero) redirige avec une erreur', function () {
    Http::preventStrayRequests();
    Http::fake(['api.zotero.org/*' => Http::response('Forbidden', 403)]);

    $etudiant = creerEtudiant();
    creerCredential($etudiant);

    $this->actingAs($etudiant)
        ->post('/etudiant/references/sync')
        ->assertRedirect();

    $this->assertDatabaseEmpty('etudiant_references');
});

// ─── saveCredential() ─────────────────────────────────────────────────────────

test("l'étudiant peut enregistrer une clé API Zotero valide", function () {
    Http::preventStrayRequests();
    Http::fake(['api.zotero.org/*' => Http::response([], 200)]);

    $etudiant = creerEtudiant();

    $this->actingAs($etudiant)
        ->post('/etudiant/zotero/credential', [
            'zotero_user_id' => '12345678',
            'api_key' => 'ValidApiKeyAbCdEfGh',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('etudiant_zotero_credentials', [
        'user_id' => $etudiant->id,
        'zotero_user_id' => '12345678',
    ]);

    // La clé ne doit jamais être stockée en clair
    $credential = EtudiantZoteroCredential::where('user_id', $etudiant->id)->first();
    expect($credential->api_key)->toBe('ValidApiKeyAbCdEfGh')
        ->and(EtudiantZoteroCredential::whereRaw('api_key = ?', ['ValidApiKeyAbCdEfGh'])->exists())->toBeFalse();
});

test('saveCredential rejette une clé API invalide (403 Zotero)', function () {
    Http::preventStrayRequests();
    Http::fake(['api.zotero.org/*' => Http::response('Forbidden', 403)]);

    $etudiant = creerEtudiant();

    $this->actingAs($etudiant)
        ->post('/etudiant/zotero/credential', [
            'zotero_user_id' => '12345678',
            'api_key' => 'CleInvalide',
        ])
        ->assertSessionHasErrors('api_key');

    $this->assertDatabaseEmpty('etudiant_zotero_credentials');
});

test('saveCredential rejette un user_id non numérique', function () {
    $etudiant = creerEtudiant();

    $this->actingAs($etudiant)
        ->post('/etudiant/zotero/credential', [
            'zotero_user_id' => 'pas-un-nombre',
            'api_key' => 'CleApi123',
        ])
        ->assertSessionHasErrors('zotero_user_id');
});

// ─── destroyCredential() ──────────────────────────────────────────────────────

test('destroyCredential supprime les credentials et les références Zotero, mais pas les manuelles', function () {
    $etudiant = creerEtudiant();
    creerCredential($etudiant);

    // Référence Zotero — doit être supprimée
    creerReference($etudiant, ['zotero_item_key' => 'ZOT123', 'titre' => 'Article Zotero']);

    // Référence manuelle — doit être conservée
    creerReference($etudiant, ['titre' => 'Note personnelle', 'ordre' => 2]);

    $this->actingAs($etudiant)
        ->delete('/etudiant/zotero/credential')
        ->assertRedirect();

    $this->assertDatabaseEmpty('etudiant_zotero_credentials');
    $this->assertDatabaseMissing('etudiant_references', ['zotero_item_key' => 'ZOT123']);
    $this->assertDatabaseHas('etudiant_references', ['titre' => 'Note personnelle']);
});
