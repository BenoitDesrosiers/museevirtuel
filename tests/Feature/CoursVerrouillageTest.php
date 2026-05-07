<?php

use App\Models\Classe;
use App\Models\Cours;
use App\Models\User;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Crée un enseignant, un cours et un étudiant inscrit dans une classe de ce cours.
 *
 * @return array{enseignant: User, cours: Cours, etudiant: User}
 */
function scenarioVerrouillage(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    $cours = Cours::create([
        'nom_cours' => 'Cours verrouillable',
        'code' => '330-VRR',
        'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);

    $etudiant = User::factory()->create(['role' => 'etudiant']);
    $classe = Classe::create(['cours_id' => $cours->id]);
    $classe->etudiants()->attach($etudiant->id);

    return compact('enseignant', 'cours', 'etudiant');
}

// ─── toggleVerrouillage() ─────────────────────────────────────────────────────

test("l'enseignant peut verrouiller son cours", function () {
    ['enseignant' => $enseignant, 'cours' => $cours] = scenarioVerrouillage();

    $this->actingAs($enseignant)
        ->patch("/cours/{$cours->id}/verrouillage")
        ->assertRedirect();

    $this->assertDatabaseHas('cours', [
        'id' => $cours->id,
        'is_verrouille' => true,
    ]);
});

test("l'enseignant peut deverrouiller son cours", function () {
    ['enseignant' => $enseignant, 'cours' => $cours] = scenarioVerrouillage();

    // Premier appel : verrouille
    $this->actingAs($enseignant)
        ->patch("/cours/{$cours->id}/verrouillage");

    // Deuxième appel : déverrouille
    $this->actingAs($enseignant)
        ->patch("/cours/{$cours->id}/verrouillage");

    $this->assertDatabaseHas('cours', [
        'id' => $cours->id,
        'is_verrouille' => false,
    ]);
});

test('un autre enseignant ne peut pas verrouiller le cours', function () {
    ['cours' => $cours] = scenarioVerrouillage();
    $autreEnseignant = User::factory()->create(['role' => 'enseignant']);

    $this->actingAs($autreEnseignant)
        ->patch("/cours/{$cours->id}/verrouillage")
        ->assertForbidden();
});

test('un etudiant ne peut pas acces a la route de verrouillage', function () {
    ['cours' => $cours, 'etudiant' => $etudiant] = scenarioVerrouillage();

    $this->actingAs($etudiant)
        ->patch("/cours/{$cours->id}/verrouillage")
        ->assertRedirect(); // Redirigé car pas dans le groupe enseignant
});

// ─── CoursController::index() — masquage étudiant ────────────────────────────

test("un cours verrouille n'apparait pas dans la liste de l'etudiant", function () {
    ['cours' => $cours, 'etudiant' => $etudiant] = scenarioVerrouillage();

    // Verrouiller le cours directement en DB
    $cours->update(['is_verrouille' => true]);

    $response = $this->actingAs($etudiant)
        ->get('/cours')
        ->assertOk();

    $coursRetournes = collect($response->inertiaProps('cours'));
    expect($coursRetournes->pluck('id'))->not->toContain($cours->id);
});

test("un cours deverrouille apparait dans la liste de l'etudiant", function () {
    ['cours' => $cours, 'etudiant' => $etudiant] = scenarioVerrouillage();

    $cours->update(['is_verrouille' => false]);

    $response = $this->actingAs($etudiant)
        ->get('/cours')
        ->assertOk();

    $coursRetournes = collect($response->inertiaProps('cours'));
    expect($coursRetournes->pluck('id'))->toContain($cours->id);
});
