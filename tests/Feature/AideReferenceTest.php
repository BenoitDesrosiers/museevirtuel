<?php

use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\TypeProjet;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Crée le scénario minimal pour tester aide_reference :
 * enseignant, cours, classe, groupe et un étudiant membre.
 *
 * @param  array<string, mixed>  $typeProjetOverrides
 * @return array{enseignant: User, typeProjet: TypeProjet, cours: Cours, cs: Classe, groupe: Groupe, etudiant: User}
 */
function creerScenarioAideRef(array $typeProjetOverrides = []): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    $typeProjet = TypeProjet::create(array_merge([
        'enseignant_id' => $enseignant->id,
        'nom' => 'Projet aide référence',
        'accessible' => true,
        'aide_reference' => false,
    ], $typeProjetOverrides));

    $cours = Cours::create([
        'nom_cours' => 'Histoire du Québec',
        'code' => '330-APA',
        'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);

    $etudiant = User::factory()->create(['role' => 'etudiant']);
    $cs = Classe::create(['cours_id' => $cours->id]);
    $cs->etudiants()->attach($etudiant->id);
    $groupe = Groupe::create(['classe_id' => $cs->id, 'created_by' => $etudiant->id]);
    $groupe->membres()->attach($etudiant->id);

    return compact('enseignant', 'typeProjet', 'cours', 'cs', 'groupe', 'etudiant');
}

// ─── TypeProjet store ─────────────────────────────────────────────────────────

it('peut créer un type de projet avec aide_reference activée', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = Cours::create([
        'nom_cours' => 'Cours test', 'code' => '330-T1', 'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);

    $this->actingAs($enseignant)
        ->postJson("/cours/{$cours->id}/types-projets", [
            'nom' => 'Projet avec aide',
            'aide_reference' => true,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('types_projets', [
        'cours_id' => $cours->id,
        'nom' => 'Projet avec aide',
        'aide_reference' => true,
    ]);
});

it('aide_reference est false par défaut à la création', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = Cours::create([
        'nom_cours' => 'Cours test', 'code' => '330-T1', 'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);

    $this->actingAs($enseignant)
        ->postJson("/cours/{$cours->id}/types-projets", ['nom' => 'Projet sans aide'])
        ->assertRedirect();

    $this->assertDatabaseHas('types_projets', [
        'cours_id' => $cours->id,
        'nom' => 'Projet sans aide',
        'aide_reference' => false,
    ]);
});

// ─── TypeProjet update ────────────────────────────────────────────────────────

it('peut activer aide_reference via update', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = Cours::create([
        'nom_cours' => 'Cours test', 'code' => '330-T1', 'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);
    $typeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id, 'cours_id' => $cours->id,
        'nom' => 'Projet', 'accessible' => false, 'aide_reference' => false,
    ]);

    $this->actingAs($enseignant)
        ->putJson("/cours/{$cours->id}/types-projets/{$typeProjet->id}", [
            'nom' => 'Projet', 'aide_reference' => true,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('types_projets', [
        'id' => $typeProjet->id, 'aide_reference' => true,
    ]);
});

it('peut désactiver aide_reference via update', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = Cours::create([
        'nom_cours' => 'Cours test', 'code' => '330-T1', 'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);
    $typeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id, 'cours_id' => $cours->id,
        'nom' => 'Projet', 'accessible' => false, 'aide_reference' => true,
    ]);

    $this->actingAs($enseignant)
        ->putJson("/cours/{$cours->id}/types-projets/{$typeProjet->id}", [
            'nom' => 'Projet', 'aide_reference' => false,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('types_projets', [
        'id' => $typeProjet->id, 'aide_reference' => false,
    ]);
});

it('update préserve aide_reference quand le paramètre est absent', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = Cours::create([
        'nom_cours' => 'Cours test', 'code' => '330-T1', 'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);
    $typeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id, 'cours_id' => $cours->id,
        'nom' => 'Projet', 'accessible' => false, 'aide_reference' => true,
    ]);

    // Aucun champ aide_reference dans la requête → doit conserver true
    $this->actingAs($enseignant)
        ->putJson("/cours/{$cours->id}/types-projets/{$typeProjet->id}", [
            'nom' => 'Projet renommé',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('types_projets', [
        'id' => $typeProjet->id,
        'nom' => 'Projet renommé',
        'aide_reference' => true,
    ]);
});

// ─── Page Show : prop aideReference ──────────────────────────────────────────

it('transmet aideReference=true à la vue Show du projet', function () {
    [
        'enseignant' => $enseignant,
        'typeProjet' => $typeProjet,
        'cours' => $cours,
        'cs' => $cs,
        'groupe' => $groupe,
        'etudiant' => $etudiant,
    ] = creerScenarioAideRef(['aide_reference' => true]);

    $this->actingAs($etudiant)
        ->get("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/edit")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('aideReference', true)
        );
});

it('transmet aideReference=false à la vue Show du projet', function () {
    [
        'enseignant' => $enseignant,
        'typeProjet' => $typeProjet,
        'cours' => $cours,
        'cs' => $cs,
        'groupe' => $groupe,
        'etudiant' => $etudiant,
    ] = creerScenarioAideRef(['aide_reference' => false]);

    $this->actingAs($etudiant)
        ->get("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/edit")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('aideReference', false)
        );
});

// ─── Modèle ───────────────────────────────────────────────────────────────────

it('cast aide_reference en booléen dans le modèle TypeProjet', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    $typeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id,
        'nom' => 'Test cast',
        'accessible' => false,
        'aide_reference' => true,
    ]);

    $typeProjet->refresh();

    expect($typeProjet->aide_reference)->toBe(true)
        ->and(gettype($typeProjet->aide_reference))->toBe('boolean');
});
