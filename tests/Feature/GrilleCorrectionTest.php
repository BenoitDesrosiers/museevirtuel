<?php

use App\Models\GrilleCorrection;
use App\Models\TypeProjet;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Crée un enseignant, un type de projet et une grille de correction rattachée.
 *
 * @return array{enseignant: User, typeProjet: TypeProjet, grille: GrilleCorrection}
 */
function creerContexteGrille(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    $typeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id,
        'nom' => 'Projet de recherche',
        'accessible' => false,
    ]);

    $grille = GrilleCorrection::create([
        'type_projet_id' => $typeProjet->id,
        'nom' => 'Grille test',
        'description' => 'Description test',
    ]);

    $grille->criteres()->createMany([
        ['label' => 'Compréhension', 'ponderation' => 60, 'ordre' => 0],
        ['label' => 'Rédaction',     'ponderation' => 40, 'ordre' => 1],
    ]);

    return compact('enseignant', 'typeProjet', 'grille');
}

/**
 * Retourne un payload valide pour créer une grille (somme = 100).
 *
 * @return array<string, mixed>
 */
function payloadGrilleValide(): array
{
    return [
        'nom' => 'Ma grille',
        'description' => 'Une description',
        'criteres' => [
            ['label' => 'Argumentation', 'ponderation' => 70],
            ['label' => 'Présentation',  'ponderation' => 30],
        ],
        'malus' => [],
    ];
}

// ─── Edit (page création/édition) ─────────────────────────────────────────────

test("l'enseignant peut accéder à la page de gestion de la grille de son type de projet", function () {
    ['enseignant' => $enseignant, 'typeProjet' => $typeProjet] = creerContexteGrille();

    $this->actingAs($enseignant)
        ->get("/types-projets/{$typeProjet->id}/grille")
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('GrilleCorrection/Edit')
        );
});

test("un enseignant ne peut pas accéder à la grille d'un type de projet qui ne lui appartient pas", function () {
    ['typeProjet' => $typeProjet] = creerContexteGrille();

    $autreEnseignant = User::factory()->create(['role' => 'enseignant']);

    $this->actingAs($autreEnseignant)
        ->get("/types-projets/{$typeProjet->id}/grille")
        ->assertForbidden();
});

test('un étudiant est redirigé depuis la page de gestion de la grille (rôle insuffisant)', function () {
    ['typeProjet' => $typeProjet] = creerContexteGrille();

    $etudiant = User::factory()->create(['role' => 'etudiant']);

    $this->actingAs($etudiant)
        ->get("/types-projets/{$typeProjet->id}/grille")
        ->assertRedirect();
});

// ─── Store ────────────────────────────────────────────────────────────────────

test("l'enseignant peut créer une grille de correction pour son type de projet", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    $typeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id,
        'nom' => 'Projet sciences',
        'accessible' => false,
    ]);

    $this->actingAs($enseignant)
        ->post("/types-projets/{$typeProjet->id}/grille", payloadGrilleValide())
        ->assertRedirect('/types-projets');

    $this->assertDatabaseHas('grilles_correction', [
        'nom' => 'Ma grille',
        'type_projet_id' => $typeProjet->id,
    ]);
    $this->assertDatabaseHas('grille_criteres', ['label' => 'Argumentation', 'ponderation' => 70]);
    $this->assertDatabaseHas('grille_criteres', ['label' => 'Présentation',  'ponderation' => 30]);
});

test("l'enseignant peut créer une grille avec des malus", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    $typeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id,
        'nom' => 'Projet maths',
        'accessible' => false,
    ]);

    $payload = payloadGrilleValide();
    $payload['malus'] = [
        ['label' => 'Fautes de français', 'deduction' => 2, 'description' => ''],
    ];

    $this->actingAs($enseignant)
        ->post("/types-projets/{$typeProjet->id}/grille", $payload)
        ->assertRedirect('/types-projets');

    $this->assertDatabaseHas('grille_malus', [
        'label' => 'Fautes de français',
        'deduction' => 2,
    ]);
});

test('la somme des pondérations doit être exactement 100', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    $typeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id,
        'nom' => 'Projet physique',
        'accessible' => false,
    ]);

    $payload = payloadGrilleValide();
    $payload['criteres'][1]['ponderation'] = 20; // total = 90

    $this->actingAs($enseignant)
        ->postJson("/types-projets/{$typeProjet->id}/grille", $payload)
        ->assertUnprocessable();
});

test('chaque critère doit avoir un libellé non vide', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    $typeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id,
        'nom' => 'Projet chimie',
        'accessible' => false,
    ]);

    $payload = payloadGrilleValide();
    $payload['criteres'][0]['label'] = '';

    $this->actingAs($enseignant)
        ->postJson("/types-projets/{$typeProjet->id}/grille", $payload)
        ->assertUnprocessable();
});

test('la liste de critères ne peut pas être vide', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    $typeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id,
        'nom' => 'Projet biologie',
        'accessible' => false,
    ]);

    $payload = payloadGrilleValide();
    $payload['criteres'] = [];

    $this->actingAs($enseignant)
        ->postJson("/types-projets/{$typeProjet->id}/grille", $payload)
        ->assertUnprocessable();
});

test("l'enseignant ne peut pas créer une seconde grille si le type de projet en a déjà une", function () {
    ['enseignant' => $enseignant, 'typeProjet' => $typeProjet] = creerContexteGrille();

    // Le type de projet a déjà une grille — le store doit être refusé
    $this->actingAs($enseignant)
        ->post("/types-projets/{$typeProjet->id}/grille", payloadGrilleValide())
        ->assertForbidden();
});

// ─── Update ───────────────────────────────────────────────────────────────────

test("l'enseignant peut modifier la grille de son type de projet", function () {
    ['enseignant' => $enseignant, 'typeProjet' => $typeProjet, 'grille' => $grille] = creerContexteGrille();

    $critere = $grille->criteres->first();

    $this->actingAs($enseignant)
        ->put("/types-projets/{$typeProjet->id}/grille", [
            'nom' => 'Grille modifiée',
            'criteres' => [
                ['id' => $critere->id, 'label' => 'Compréhension', 'ponderation' => 50],
                ['label' => 'Nouveau critère', 'ponderation' => 50],
            ],
            'malus' => [],
        ])
        ->assertRedirect('/types-projets');

    $this->assertDatabaseHas('grilles_correction', ['id' => $grille->id, 'nom' => 'Grille modifiée']);
    $this->assertDatabaseHas('grille_criteres', ['label' => 'Nouveau critère', 'ponderation' => 50]);
});

test('un critère retiré lors de la mise à jour est supprimé de la base', function () {
    ['enseignant' => $enseignant, 'typeProjet' => $typeProjet, 'grille' => $grille] = creerContexteGrille();

    $critereRetire = $grille->criteres->last();

    $this->actingAs($enseignant)
        ->put("/types-projets/{$typeProjet->id}/grille", [
            'nom' => 'Grille modifiée',
            'criteres' => [
                ['id' => $grille->criteres->first()->id, 'label' => 'Compréhension', 'ponderation' => 100],
            ],
            'malus' => [],
        ])
        ->assertRedirect('/types-projets');

    $this->assertDatabaseMissing('grille_criteres', ['id' => $critereRetire->id]);
});

test("un enseignant ne peut pas modifier la grille d'un type de projet qui ne lui appartient pas", function () {
    ['typeProjet' => $typeProjet] = creerContexteGrille();

    $autreEnseignant = User::factory()->create(['role' => 'enseignant']);

    $this->actingAs($autreEnseignant)
        ->put("/types-projets/{$typeProjet->id}/grille", payloadGrilleValide())
        ->assertForbidden();
});

// ─── Destroy ──────────────────────────────────────────────────────────────────

test("l'enseignant peut supprimer la grille de son type de projet", function () {
    ['enseignant' => $enseignant, 'typeProjet' => $typeProjet, 'grille' => $grille] = creerContexteGrille();

    $this->actingAs($enseignant)
        ->delete("/types-projets/{$typeProjet->id}/grille")
        ->assertRedirect('/types-projets');

    $this->assertDatabaseMissing('grilles_correction', ['id' => $grille->id]);
});

test("un enseignant ne peut pas supprimer la grille d'un type de projet qui ne lui appartient pas", function () {
    ['typeProjet' => $typeProjet] = creerContexteGrille();

    $autreEnseignant = User::factory()->create(['role' => 'enseignant']);

    $this->actingAs($autreEnseignant)
        ->delete("/types-projets/{$typeProjet->id}/grille")
        ->assertForbidden();
});
