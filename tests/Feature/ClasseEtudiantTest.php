<?php

use App\Actions\InscrireEtudiantClasseAction;
use App\Exceptions\TransactionEchoueeException;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

function creerContexteClasseEtudiant(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = Cours::create([
        'nom_cours' => 'Sciences humaines',
        'description' => 'Cours test',
        'code' => '387-TEST',
        'groupe' => 'A',
        'enseignant_id' => $enseignant->id,
    ]);

    $classe = Classe::forceCreate([
        'cours_id' => $cours->id,
        'numero' => '00001',
        'code' => $cours->code,
        'nom' => 'Classe 00001',
        'jour_semaine' => 'Lundi',
        'plage_horaire' => '08:30 - 11:30',
    ]);

    return compact('enseignant', 'cours', 'classe');
}

test('store ajoute un etudiant a une section', function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classe' => $classe] = creerContexteClasseEtudiant();

    $this->actingAs($enseignant)
        ->post("/cours/{$cours->id}/classes/{$classe->id}/etudiants", [
            'prenom' => 'Alice',
            'nom' => 'Tremblay',
            'email' => 'alice.tremblay@example.com',
            'no_da' => '1234567',
            'statut_cours' => 'actif',
        ])
        ->assertRedirect();

    $etudiant = User::where('no_da', '1234567')->first();
    expect($etudiant)->not->toBeNull();

    $this->assertDatabaseHas('classe_etudiant', [
        'classe_id' => $classe->id,
        'user_id' => $etudiant->id,
        'statut_cours' => 'actif',
    ]);
});

test('store utilise actif comme statut par défaut', function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classe' => $classe] = creerContexteClasseEtudiant();

    $this->actingAs($enseignant)
        ->post("/cours/{$cours->id}/classes/{$classe->id}/etudiants", [
            'prenom' => 'Claire',
            'nom' => 'Roy',
            'email' => 'claire.roy@example.com',
            'no_da' => '1234568',
        ])
        ->assertRedirect();

    $etudiant = User::where('no_da', '1234568')->firstOrFail();

    $this->assertDatabaseHas('classe_etudiant', [
        'classe_id' => $classe->id,
        'user_id' => $etudiant->id,
        'statut_cours' => 'actif',
    ]);
});

test('store refuse un statut de cours qui ne fait pas partie de enum', function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classe' => $classe] = creerContexteClasseEtudiant();

    $this->actingAs($enseignant)
        ->postJson("/cours/{$cours->id}/classes/{$classe->id}/etudiants", [
            'prenom' => 'Claire',
            'nom' => 'Roy',
            'email' => 'claire.roy@example.com',
            'no_da' => '1234568',
            'statut_cours' => 'ancien',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['statut_cours']);
});

test('update modifie etudiant et statut dans la section', function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classe' => $classe] = creerContexteClasseEtudiant();
    $etudiant = User::factory()->create([
        'role' => 'etudiant',
        'prenom' => 'Bob',
        'nom' => 'Lavoie',
        'email' => 'bob@example.com',
        'no_da' => '7654321',
    ]);
    $classe->etudiants()->attach($etudiant->id, ['statut_cours' => 'ancien']);

    $this->actingAs($enseignant)
        ->put("/cours/{$cours->id}/classes/{$classe->id}/etudiants/{$etudiant->id}", [
            'prenom' => 'Robert',
            'nom' => 'Lavoie',
            'email' => 'robert@example.com',
            'no_da' => '7654321',
            'statut_cours' => 'actif',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('users', [
        'id' => $etudiant->id,
        'prenom' => 'Robert',
        'email' => 'robert@example.com',
    ]);
    $this->assertDatabaseHas('classe_etudiant', [
        'classe_id' => $classe->id,
        'user_id' => $etudiant->id,
        'statut_cours' => 'actif',
    ]);
});

test('update refuse un statut de cours invalide', function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classe' => $classe] = creerContexteClasseEtudiant();
    $etudiant = User::factory()->create([
        'role' => 'etudiant',
        'no_da' => '7654322',
    ]);
    $classe->etudiants()->attach($etudiant->id, ['statut_cours' => 'actif']);

    $this->actingAs($enseignant)
        ->putJson("/cours/{$cours->id}/classes/{$classe->id}/etudiants/{$etudiant->id}", [
            'prenom' => $etudiant->prenom,
            'nom' => $etudiant->nom,
            'email' => $etudiant->email,
            'no_da' => $etudiant->no_da,
            'statut_cours' => 'ancien',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['statut_cours']);
});

test('destroy retire etudiant de la section', function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classe' => $classe] = creerContexteClasseEtudiant();
    $etudiant = User::factory()->create(['role' => 'etudiant']);
    $classe->etudiants()->attach($etudiant->id);

    $this->actingAs($enseignant)
        ->delete("/cours/{$cours->id}/classes/{$classe->id}/etudiants/{$etudiant->id}")
        ->assertRedirect();

    $this->assertDatabaseMissing('classe_etudiant', [
        'classe_id' => $classe->id,
        'user_id' => $etudiant->id,
    ]);
});

test('import ajoute des etudiants depuis csv', function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classe' => $classe] = creerContexteClasseEtudiant();

    $csv = "No de DA;Nom de l'étudiant;Prénom de l'étudiant;Statut du cours\n123001;Bouchard;Lea;actif\n123002;Gagne;Noah;inactif\n";
    $file = UploadedFile::fake()->createWithContent('etudiants.csv', $csv);

    $this->actingAs($enseignant)
        ->post("/cours/{$cours->id}/classes/{$classe->id}/etudiants/import", [
            'csv' => $file,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('users', ['no_da' => '123001']);
    $this->assertDatabaseHas('users', ['no_da' => '123002']);
    $this->assertDatabaseMissing('users', ['no_da' => 'No de DA']);
});

test('import ajoute dans une classe les etudiants deja inscrits ailleurs depuis un csv sans entete', function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classe' => $classe] = creerContexteClasseEtudiant();
    $autreClasse = Classe::forceCreate([
        'cours_id' => $cours->id,
        'numero' => '00002',
        'code' => $cours->code,
        'nom' => 'Classe 00002',
        'jour_semaine' => 'Mardi',
        'plage_horaire' => '08:30 - 11:30',
    ]);
    $etudiant = User::factory()->create([
        'role' => 'etudiant',
        'no_da' => '240101',
    ]);
    $autreClasse->etudiants()->attach($etudiant->id);

    $csv = "240101;Tremblay;Alexis;Actif\n240102;Gagnon;Camille;Actif\n";
    $file = UploadedFile::fake()->createWithContent('etudiants.csv', $csv);

    $this->actingAs($enseignant)
        ->post("/cours/{$cours->id}/classes/{$classe->id}/etudiants/import", [
            'csv' => $file,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('classe_etudiant', [
        'classe_id' => $classe->id,
        'user_id' => $etudiant->id,
        'no_da' => '240101',
        'statut_cours' => 'Actif',
    ]);
    $this->assertDatabaseHas('classe_etudiant', [
        'classe_id' => $classe->id,
        'user_id' => User::query()->where('no_da', '240102')->sole()->id,
        'no_da' => '240102',
        'statut_cours' => 'Actif',
    ]);
});

test('store retourne 403 pour un utilisateur non autorise', function () {
    ['cours' => $cours, 'classe' => $classe] = creerContexteClasseEtudiant();
    $etudiant = User::factory()->create(['role' => 'etudiant']);

    $this->actingAs($etudiant)
        ->post("/cours/{$cours->id}/classes/{$classe->id}/etudiants", [
            'prenom' => 'Alice',
            'nom' => 'Tremblay',
            'email' => 'alice.tremblay@example.com',
            'no_da' => '1234567',
            'statut_cours' => 'actif',
        ])
        ->assertRedirect();

    $this->assertDatabaseMissing('users', ['no_da' => '1234567']);
});

test("update refuse un étudiant qui n'est pas inscrit dans la classe", function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classe' => $classe] = creerContexteClasseEtudiant();
    $autre = User::factory()->create(['role' => 'etudiant', 'prenom' => 'Intact']);

    $this->actingAs($enseignant)
        ->put("/cours/{$cours->id}/classes/{$classe->id}/etudiants/{$autre->id}", [
            'prenom' => 'Pirate',
            'nom' => 'Lavoie',
            'email' => 'pirate@example.com',
            'no_da' => '1234567',
            'statut_cours' => 'actif',
        ])
        ->assertNotFound();

    expect($autre->fresh()->prenom)->toBe('Intact');
});

// ─── B8 — Le courriel ne doit jamais récupérer le compte d'un autre utilisateur ───

/**
 * Données d'ajout manuel d'un étudiant ; les valeurs passées en paramètre ont priorité.
 *
 * @param  array<string, mixed>  $surcharges
 * @return array<string, mixed>
 */
function donneesAjoutEtudiant(array $surcharges = []): array
{
    return array_merge([
        'prenom' => 'Alice',
        'nom' => 'Tremblay',
        'no_da' => '2223334',
        'statut_cours' => 'actif',
    ], $surcharges);
}

test('store refuse le courriel d\'un compte qui n\'est pas étudiant', function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classe' => $classe] = creerContexteClasseEtudiant();
    $collegue = User::factory()->create(['role' => 'enseignant', 'email' => 'collegue@example.com']);

    $this->actingAs($enseignant)
        ->post("/cours/{$cours->id}/classes/{$classe->id}/etudiants", donneesAjoutEtudiant(['email' => 'collegue@example.com']))
        ->assertSessionHasErrors('email');

    expect($classe->etudiants()->count())->toBe(0)
        ->and($collegue->fresh()->role)->toBe('enseignant');
});

test('store refuse le courriel d\'un étudiant qui a un autre DA', function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classe' => $classe] = creerContexteClasseEtudiant();
    User::factory()->create(['role' => 'etudiant', 'no_da' => '9998887', 'email' => 'autre@example.com']);

    $this->actingAs($enseignant)
        ->post("/cours/{$cours->id}/classes/{$classe->id}/etudiants", donneesAjoutEtudiant(['email' => 'autre@example.com']))
        ->assertSessionHasErrors('email');

    expect($classe->etudiants()->count())->toBe(0);
});

test('store reprend l\'étudiant existant qui a le même DA', function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classe' => $classe] = creerContexteClasseEtudiant();
    $existant = User::factory()->create(['role' => 'etudiant', 'no_da' => '2223334', 'email' => 'alice@example.com']);

    $this->actingAs($enseignant)
        ->post("/cours/{$cours->id}/classes/{$classe->id}/etudiants", donneesAjoutEtudiant(['email' => 'alice@example.com']))
        ->assertSessionHasNoErrors();

    expect($classe->etudiants()->pluck('users.id')->all())->toBe([$existant->id])
        ->and(User::count())->toBe(2);
});

test('store refuse un étudiant déjà inscrit ailleurs sans créer de compte', function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classe' => $classe] = creerContexteClasseEtudiant();
    $autreClasse = Classe::create(['cours_id' => $cours->id, 'code' => 'AUTRE']);
    $inscrit = User::factory()->create(['role' => 'etudiant', 'no_da' => '2223334']);
    $autreClasse->etudiants()->attach($inscrit->id, ['no_da' => '2223334', 'statut_cours' => 'actif']);
    $comptes = User::count();

    $this->actingAs($enseignant)
        ->post("/cours/{$cours->id}/classes/{$classe->id}/etudiants", donneesAjoutEtudiant(['email' => 'nouveau@example.com']))
        ->assertSessionHasErrors(['no_da' => __('etudiant.already_in_class')]);

    expect(User::count())->toBe($comptes)
        ->and($classe->etudiants()->count())->toBe(0);
});

test('l\'inscription manuelle annule la création du compte si l\'inscription échoue', function () {
    // Une classe jamais enregistrée fait échouer l'attach (classe_id nul) après la création du compte
    $classeFantome = new Classe;

    expect(fn () => app(InscrireEtudiantClasseAction::class)->execute($classeFantome, donneesAjoutEtudiant()))
        ->toThrow(TransactionEchoueeException::class);

    expect(User::where('no_da', '2223334')->exists())->toBeFalse();
});
