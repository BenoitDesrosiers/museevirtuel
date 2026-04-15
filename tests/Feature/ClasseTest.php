<?php

use App\Models\Classe;
use App\Models\Cours;
use App\Models\Thematique;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Crée un scénario de test complet :
 * - 1 enseignant avec 3 thématiques
 * - 1 cours
 * - 4 étudiants inscrits dans le cours
 *
 * @return array{enseignant: User, cours: Cours, t1: Thematique, t2: Thematique, t3: Thematique, alice: User, bob: User, claire: User, david: User}
 */
function creerContexteCours(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    $cours = Cours::create([
        'nom_cours' => 'Histoire du Québec',
        'description' => 'Cours test',
        'code' => '330-TEST',
        'groupe' => 'A',
        'enseignant_id' => $enseignant->id,
    ]);

    $t1 = Thematique::create(['nom' => 'La Nouvelle-France',      'enseignant_id' => $enseignant->id]);
    $t2 = Thematique::create(['nom' => 'La Révolution tranquille', 'enseignant_id' => $enseignant->id]);
    $t3 = Thematique::create(['nom' => 'Les Premières Nations',    'enseignant_id' => $enseignant->id]);

    $alice = User::factory()->create(['role' => 'etudiant']);
    $bob = User::factory()->create(['role' => 'etudiant']);
    $claire = User::factory()->create(['role' => 'etudiant']);
    $david = User::factory()->create(['role' => 'etudiant']);

    $cours->etudiants()->attach([$alice->id, $bob->id, $claire->id, $david->id]);

    return compact('enseignant', 'cours', 't1', 't2', 't3', 'alice', 'bob', 'claire', 'david');
}

/**
 * Crée une classe complète (membres + thématiques) pour les tests de show/update.
 */
function creerClasse(array $ctx, User $createur, User $membre, array $thematiques = []): Classe
{
    $classe = Classe::create([
        'cours_id' => $ctx['cours']->id,
        'created_by' => $createur->id,
    ]);

    $classe->membres()->attach([$createur->id, $membre->id]);

    if (! empty($thematiques)) {
        $classe->thematiques()->attach(array_map(fn ($t) => $t->id, $thematiques));
    }

    return $classe;
}

// ─── store() — création de la classe ──────────────────────────────────────────

test('store() crée la classe et remplit classe_etudiant', function () {
    $ctx = creerContexteCours();

    $this->actingAs($ctx['alice'])
        ->post("/cours/{$ctx['cours']->id}/classes", [
            'membres' => [$ctx['bob']->id],
        ])
        ->assertRedirect();

    $classe = Classe::where('cours_id', $ctx['cours']->id)->first();
    expect($classe)->not->toBeNull();

    // Alice (créateur) et Bob doivent être dans classe_etudiant
    $this->assertDatabaseHas('classe_etudiant', ['classe_id' => $classe->id, 'user_id' => $ctx['alice']->id]);
    $this->assertDatabaseHas('classe_etudiant', ['classe_id' => $classe->id, 'user_id' => $ctx['bob']->id]);
});

test('store() remplit classe_thematique quand des thématiques sont sélectionnées', function () {
    $ctx = creerContexteCours();

    $this->actingAs($ctx['alice'])
        ->post("/cours/{$ctx['cours']->id}/classes", [
            'membres' => [],
            'thematiques' => [$ctx['t1']->id, $ctx['t2']->id],
        ])
        ->assertRedirect();

    $classe = Classe::where('cours_id', $ctx['cours']->id)->first();

    $this->assertDatabaseHas('classe_thematique', ['classe_id' => $classe->id, 'thematique_id' => $ctx['t1']->id]);
    $this->assertDatabaseHas('classe_thematique', ['classe_id' => $classe->id, 'thematique_id' => $ctx['t2']->id]);
    expect($classe->thematiques()->count())->toBe(2);
});

test('store() ajoute toujours le créateur comme membre même sans membres sélectionnés', function () {
    $ctx = creerContexteCours();

    $this->actingAs($ctx['alice'])
        ->post("/cours/{$ctx['cours']->id}/classes", [
            'membres' => [],
        ])
        ->assertRedirect();

    $classe = Classe::where('cours_id', $ctx['cours']->id)->first();

    $this->assertDatabaseHas('classe_etudiant', ['classe_id' => $classe->id, 'user_id' => $ctx['alice']->id]);
    expect($classe->membres()->count())->toBe(1);
});

test('store() filtre silencieusement les membres non inscrits dans le cours', function () {
    $ctx = creerContexteCours();
    $etranger = User::factory()->create(['role' => 'etudiant']); // non inscrit dans le cours

    $this->actingAs($ctx['alice'])
        ->post("/cours/{$ctx['cours']->id}/classes", [
            'membres' => [$ctx['bob']->id, $etranger->id],
        ])
        ->assertRedirect();

    $classe = Classe::where('cours_id', $ctx['cours']->id)->first();

    // Bob (inscrit) doit être présent, l'étranger (non inscrit) ne doit pas l'être
    $this->assertDatabaseHas('classe_etudiant', ['classe_id' => $classe->id, 'user_id' => $ctx['bob']->id]);
    $this->assertDatabaseMissing('classe_etudiant', ['classe_id' => $classe->id, 'user_id' => $etranger->id]);
});

test('store() filtre silencieusement les thématiques hors cours', function () {
    $ctx = creerContexteCours();
    $autreEnseignant = User::factory()->create(['role' => 'enseignant']);
    $thematiqueHors = Thematique::create(['nom' => 'Thème étranger', 'enseignant_id' => $autreEnseignant->id]);

    $this->actingAs($ctx['alice'])
        ->post("/cours/{$ctx['cours']->id}/classes", [
            'membres' => [],
            'thematiques' => [$ctx['t1']->id, $thematiqueHors->id],
        ])
        ->assertRedirect();

    $classe = Classe::where('cours_id', $ctx['cours']->id)->first();

    $this->assertDatabaseHas('classe_thematique', ['classe_id' => $classe->id, 'thematique_id' => $ctx['t1']->id]);
    $this->assertDatabaseMissing('classe_thematique', ['classe_id' => $classe->id, 'thematique_id' => $thematiqueHors->id]);
});

test('store() refuse un étudiant non inscrit dans le cours (403)', function () {
    $ctx = creerContexteCours();
    $etranger = User::factory()->create(['role' => 'etudiant']);

    $this->actingAs($etranger)
        ->post("/cours/{$ctx['cours']->id}/classes", ['membres' => []])
        ->assertForbidden();
});

test("store() refuse un étudiant déjà membre d'une classe dans ce cours", function () {
    $ctx = creerContexteCours();
    creerClasse($ctx, $ctx['alice'], $ctx['bob']);

    $response = $this->actingAs($ctx['alice'])
        ->post("/cours/{$ctx['cours']->id}/classes", ['membres' => []]);

    $response->assertRedirect();
    // La classe originale ne doit pas avoir été dupliquée
    expect(Classe::where('cours_id', $ctx['cours']->id)->count())->toBe(1);
});

// ─── show() — consultation de la classe ───────────────────────────────────────

test('show() est accessible à un membre de la classe', function () {
    $ctx = creerContexteCours();
    $classe = creerClasse($ctx, $ctx['alice'], $ctx['bob'], [$ctx['t1']]);

    $this->actingAs($ctx['alice'])
        ->get("/cours/{$ctx['cours']->id}/classes/{$classe->id}")
        ->assertOk();
});

test("show() est accessible à l'enseignant du cours", function () {
    $ctx = creerContexteCours();
    $classe = creerClasse($ctx, $ctx['alice'], $ctx['bob']);

    $this->actingAs($ctx['enseignant'])
        ->get("/cours/{$ctx['cours']->id}/classes/{$classe->id}")
        ->assertOk();
});

test('show() refuse un étudiant non membre de la classe (403)', function () {
    $ctx = creerContexteCours();
    $classe = creerClasse($ctx, $ctx['alice'], $ctx['bob']);

    $this->actingAs($ctx['claire']) // inscrite dans le cours mais pas dans cette classe
        ->get("/cours/{$ctx['cours']->id}/classes/{$classe->id}")
        ->assertForbidden();
});

test('show() retourne 404 si la classe ne correspond pas au cours', function () {
    $ctx = creerContexteCours();

    $autreEnseignant = User::factory()->create(['role' => 'enseignant']);
    $autreCours = Cours::create([
        'nom_cours' => 'Autre cours', 'code' => 'X999', 'groupe' => 'B',
        'enseignant_id' => $autreEnseignant->id,
    ]);

    $classe = creerClasse($ctx, $ctx['alice'], $ctx['bob']);

    $this->actingAs($ctx['alice'])
        ->get("/cours/{$autreCours->id}/classes/{$classe->id}")
        ->assertNotFound();
});

// ─── updateMembres() ──────────────────────────────────────────────────────────

test('updateMembres() ajoute un membre et remplit classe_etudiant', function () {
    $ctx = creerContexteCours();
    $classe = creerClasse($ctx, $ctx['alice'], $ctx['bob']);

    $this->actingAs($ctx['alice'])
        ->put("/cours/{$ctx['cours']->id}/classes/{$classe->id}/membres", [
            'ajouter' => [$ctx['claire']->id],
            'retirer' => [],
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('classe_etudiant', ['classe_id' => $classe->id, 'user_id' => $ctx['claire']->id]);
});

test('updateMembres() retire un membre de classe_etudiant', function () {
    $ctx = creerContexteCours();
    $classe = creerClasse($ctx, $ctx['alice'], $ctx['bob']);

    $this->actingAs($ctx['alice'])
        ->put("/cours/{$ctx['cours']->id}/classes/{$classe->id}/membres", [
            'ajouter' => [],
            'retirer' => [$ctx['bob']->id],
        ])
        ->assertRedirect();

    $this->assertDatabaseMissing('classe_etudiant', ['classe_id' => $classe->id, 'user_id' => $ctx['bob']->id]);
});

test('updateMembres() ne permet pas au créateur de se retirer', function () {
    $ctx = creerContexteCours();
    $classe = creerClasse($ctx, $ctx['alice'], $ctx['bob']);

    $this->actingAs($ctx['alice'])
        ->put("/cours/{$ctx['cours']->id}/classes/{$classe->id}/membres", [
            'ajouter' => [],
            'retirer' => [$ctx['alice']->id],
        ])
        ->assertRedirect();

    // Alice doit toujours être membre
    $this->assertDatabaseHas('classe_etudiant', ['classe_id' => $classe->id, 'user_id' => $ctx['alice']->id]);
});

test('updateMembres() refuse un non-créateur (403)', function () {
    $ctx = creerContexteCours();
    $classe = creerClasse($ctx, $ctx['alice'], $ctx['bob']);

    $this->actingAs($ctx['bob']) // membre mais pas créateur
        ->put("/cours/{$ctx['cours']->id}/classes/{$classe->id}/membres", [
            'ajouter' => [$ctx['claire']->id],
            'retirer' => [],
        ])
        ->assertForbidden();
});

// ─── updateThematiques() ──────────────────────────────────────────────────────

test('updateThematiques() remplace les thématiques et remplit classe_thematique', function () {
    $ctx = creerContexteCours();
    $classe = creerClasse($ctx, $ctx['alice'], $ctx['bob'], [$ctx['t1']]);

    $this->actingAs($ctx['alice'])
        ->put("/cours/{$ctx['cours']->id}/classes/{$classe->id}/thematiques", [
            'thematiques' => [$ctx['t2']->id, $ctx['t3']->id],
        ])
        ->assertRedirect();

    // t1 doit être retirée, t2 et t3 doivent être présentes
    $this->assertDatabaseMissing('classe_thematique', ['classe_id' => $classe->id, 'thematique_id' => $ctx['t1']->id]);
    $this->assertDatabaseHas('classe_thematique', ['classe_id' => $classe->id, 'thematique_id' => $ctx['t2']->id]);
    $this->assertDatabaseHas('classe_thematique', ['classe_id' => $classe->id, 'thematique_id' => $ctx['t3']->id]);
});

test('updateThematiques() accepte un tableau vide (supprime tout)', function () {
    $ctx = creerContexteCours();
    $classe = creerClasse($ctx, $ctx['alice'], $ctx['bob'], [$ctx['t1'], $ctx['t2']]);

    $this->actingAs($ctx['alice'])
        ->put("/cours/{$ctx['cours']->id}/classes/{$classe->id}/thematiques", [
            'thematiques' => [],
        ])
        ->assertRedirect();

    expect($classe->thematiques()->count())->toBe(0);
});

test('updateThematiques() filtre les thématiques hors cours', function () {
    $ctx = creerContexteCours();
    $autreEnseignant = User::factory()->create(['role' => 'enseignant']);
    $thematiqueHors = Thematique::create(['nom' => 'Thème étranger', 'enseignant_id' => $autreEnseignant->id]);
    $classe = creerClasse($ctx, $ctx['alice'], $ctx['bob']);

    $this->actingAs($ctx['alice'])
        ->put("/cours/{$ctx['cours']->id}/classes/{$classe->id}/thematiques", [
            'thematiques' => [$ctx['t1']->id, $thematiqueHors->id],
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('classe_thematique', ['classe_id' => $classe->id, 'thematique_id' => $ctx['t1']->id]);
    $this->assertDatabaseMissing('classe_thematique', ['classe_id' => $classe->id, 'thematique_id' => $thematiqueHors->id]);
});

test('updateThematiques() refuse un non-membre (403)', function () {
    $ctx = creerContexteCours();
    $classe = creerClasse($ctx, $ctx['alice'], $ctx['bob']);

    $this->actingAs($ctx['claire']) // inscrite dans le cours mais pas dans la classe
        ->put("/cours/{$ctx['cours']->id}/classes/{$classe->id}/thematiques", [
            'thematiques' => [$ctx['t1']->id],
        ])
        ->assertForbidden();
});

// ─── destroy() ────────────────────────────────────────────────────────────────

test("destroy() supprime la classe — l'enseignant du cours peut supprimer", function () {
    $ctx = creerContexteCours();
    $classe = creerClasse($ctx, $ctx['alice'], $ctx['bob'], [$ctx['t1']]);

    $classeId = $classe->id;

    $this->actingAs($ctx['enseignant'])
        ->delete("/cours/{$ctx['cours']->id}/classes/{$classeId}")
        ->assertRedirect();

    $this->assertDatabaseMissing('classes', ['id' => $classeId]);
    $this->assertDatabaseMissing('classe_etudiant', ['classe_id' => $classeId]);
    $this->assertDatabaseMissing('classe_thematique', ['classe_id' => $classeId]);
});

test('destroy() refuse un enseignant étranger (403)', function () {
    $ctx = creerContexteCours();
    $classe = creerClasse($ctx, $ctx['alice'], $ctx['bob']);
    $autreEnseignant = User::factory()->create(['role' => 'enseignant']);

    $this->actingAs($autreEnseignant)
        ->delete("/cours/{$ctx['cours']->id}/classes/{$classe->id}")
        ->assertForbidden();

    $this->assertDatabaseHas('classes', ['id' => $classe->id]);
});
