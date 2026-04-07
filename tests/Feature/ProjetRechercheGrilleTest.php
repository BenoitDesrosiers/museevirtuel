<?php

use App\Models\Classe;
use App\Models\GrilleCorrection;
use App\Models\GrilleCritere;
use App\Models\GrilleMalus;
use App\Models\Groupe;
use App\Models\ProjetGrilleMalus;
use App\Models\ProjetGrilleNote;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use App\Models\User;

// ─── Helper ───────────────────────────────────────────────────────────────────

/**
 * Crée un contexte complet : enseignant, classe, type de projet avec grille, groupe, étudiant et projet.
 *
 * La grille appartient au type de projet, qui est rattaché au projet de recherche.
 *
 * @return array{
 *     enseignant: User,
 *     classe: Classe,
 *     groupe: Groupe,
 *     etudiant: User,
 *     projet: ProjetRecherche,
 *     typeProjet: TypeProjet,
 *     grille: GrilleCorrection,
 *     critere1: GrilleCritere,
 *     critere2: GrilleCritere,
 *     malus: GrilleMalus
 * }
 */
function creerContexteGrilleProjet(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    $classe = Classe::create([
        'nom_cours' => 'Histoire',
        'description' => 'Test',
        'code' => '330-G1',
        'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);

    $etudiant = User::factory()->create(['role' => 'etudiant']);
    $classe->etudiants()->attach($etudiant->id);

    $groupe = Groupe::create([
        'classe_id' => $classe->id,
        'created_by' => $etudiant->id,
    ]);
    $groupe->membres()->attach($etudiant->id);

    // La grille appartient au type de projet (et non à la classe directement)
    $typeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id,
        'nom' => 'Projet de recherche',
        'accessible' => true,
    ]);

    $grille = GrilleCorrection::create(['type_projet_id' => $typeProjet->id, 'nom' => 'Grille projet']);
    $critere1 = $grille->criteres()->create(['label' => 'Analyse',   'ponderation' => 60, 'ordre' => 0]);
    $critere2 = $grille->criteres()->create(['label' => 'Rédaction', 'ponderation' => 40, 'ordre' => 1]);
    $malus = $grille->malus()->create(['label' => 'Fautes', 'deduction' => 3, 'ordre' => 0]);

    // Le projet est rattaché au type de projet pour accéder à sa grille
    $projet = ProjetRecherche::create([
        'groupe_id' => $groupe->id,
        'type_projet_id' => $typeProjet->id,
    ]);

    return compact('enseignant', 'classe', 'groupe', 'etudiant', 'projet', 'typeProjet', 'grille', 'critere1', 'critere2')
        + ['malus' => $malus];
}

// ─── upsertNoteGrille ─────────────────────────────────────────────────────────

test("l'enseignant peut sauvegarder une note pour un critère de la grille", function () {
    [
        'enseignant' => $enseignant,
        'classe' => $classe,
        'groupe' => $groupe,
        'etudiant' => $etudiant,
        'projet' => $projet,
        'typeProjet' => $typeProjet,
        'critere1' => $critere1,
    ] = creerContexteGrilleProjet();

    $this->actingAs($enseignant)
        ->putJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/grille/notes", [
            'critere_id' => $critere1->id,
            'note' => 4,
            'user_id' => $etudiant->id,
        ])
        ->assertOk()
        ->assertJsonStructure(['message', 'noteFinaleGrilleParEtudiant']);

    $this->assertDatabaseHas('projet_grille_notes', [
        'projet_id' => $projet->id,
        'user_id' => $etudiant->id,
        'critere_id' => $critere1->id,
        'note' => 4,
    ]);
});

test('un double PUT sur le même critère/étudiant met à jour sans créer de doublon', function () {
    [
        'enseignant' => $enseignant,
        'classe' => $classe,
        'groupe' => $groupe,
        'etudiant' => $etudiant,
        'projet' => $projet,
        'typeProjet' => $typeProjet,
        'critere1' => $critere1,
    ] = creerContexteGrilleProjet();

    $url = "/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/grille/notes";

    $this->actingAs($enseignant)->putJson($url, ['critere_id' => $critere1->id, 'note' => 2, 'user_id' => $etudiant->id]);
    $this->actingAs($enseignant)->putJson($url, ['critere_id' => $critere1->id, 'note' => 4, 'user_id' => $etudiant->id])->assertOk();

    expect(
        ProjetGrilleNote::where('projet_id', $projet->id)
            ->where('critere_id', $critere1->id)
            ->where('user_id', $etudiant->id)
            ->count()
    )->toBe(1);

    $this->assertDatabaseHas('projet_grille_notes', ['critere_id' => $critere1->id, 'note' => 4]);
});

test('un critère hors de la grille du type de projet est refusé (protection IDOR)', function () {
    [
        'enseignant' => $enseignant,
        'classe' => $classe,
        'groupe' => $groupe,
        'etudiant' => $etudiant,
        'typeProjet' => $typeProjet,
    ] = creerContexteGrilleProjet();

    // Crée un autre type de projet avec une autre grille (critère étranger)
    $autreType = TypeProjet::create([
        'enseignant_id' => $enseignant->id,
        'nom' => 'Autre type',
        'accessible' => false,
    ]);
    $autreGrille = GrilleCorrection::create(['type_projet_id' => $autreType->id, 'nom' => 'Autre grille']);
    $autreCritere = $autreGrille->criteres()->create(['label' => 'Hors grille', 'ponderation' => 100, 'ordre' => 0]);

    $this->actingAs($enseignant)
        ->putJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/grille/notes", [
            'critere_id' => $autreCritere->id,
            'note' => 4,
            'user_id' => $etudiant->id,
        ])
        ->assertUnprocessable();
});

test('noter un étudiant hors du groupe est refusé (protection IDOR)', function () {
    [
        'enseignant' => $enseignant,
        'classe' => $classe,
        'groupe' => $groupe,
        'typeProjet' => $typeProjet,
        'critere1' => $critere1,
    ] = creerContexteGrilleProjet();

    $etudiantHors = User::factory()->create(['role' => 'etudiant']);
    $classe->etudiants()->attach($etudiantHors->id);

    $this->actingAs($enseignant)
        ->putJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/grille/notes", [
            'critere_id' => $critere1->id,
            'note' => 3,
            'user_id' => $etudiantHors->id,
        ])
        ->assertStatus(422);
});

test('un étudiant ne peut pas sauvegarder une note grille', function () {
    [
        'etudiant' => $etudiant,
        'classe' => $classe,
        'groupe' => $groupe,
        'typeProjet' => $typeProjet,
        'critere1' => $critere1,
    ] = creerContexteGrilleProjet();

    $this->actingAs($etudiant)
        ->putJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/grille/notes", [
            'critere_id' => $critere1->id,
            'note' => 3,
            'user_id' => $etudiant->id,
        ])
        ->assertForbidden();
});

it('accepte uniquement les notes 0, 2, 3 et 4 pour une note grille', function (int $note) {
    [
        'enseignant' => $enseignant,
        'classe' => $classe,
        'groupe' => $groupe,
        'etudiant' => $etudiant,
        'typeProjet' => $typeProjet,
        'critere1' => $critere1,
    ] = creerContexteGrilleProjet();

    $this->actingAs($enseignant)
        ->putJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/grille/notes", [
            'critere_id' => $critere1->id,
            'note' => $note,
            'user_id' => $etudiant->id,
        ])
        ->assertUnprocessable();
})->with([1, 5, -1, 99]);

// ─── toggleMalusGrille ────────────────────────────────────────────────────────

test("l'enseignant peut appliquer un malus à un étudiant", function () {
    [
        'enseignant' => $enseignant,
        'classe' => $classe,
        'groupe' => $groupe,
        'etudiant' => $etudiant,
        'projet' => $projet,
        'typeProjet' => $typeProjet,
        'malus' => $malus,
    ] = creerContexteGrilleProjet();

    $this->actingAs($enseignant)
        ->putJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/grille/malus", [
            'malus_id' => $malus->id,
            'user_id' => $etudiant->id,
            'applique' => true,
        ])
        ->assertOk()
        ->assertJsonStructure(['message', 'noteFinaleGrilleParEtudiant']);

    $this->assertDatabaseHas('projet_grille_malus', [
        'projet_id' => $projet->id,
        'user_id' => $etudiant->id,
        'malus_id' => $malus->id,
        'applique' => true,
    ]);
});

test("l'enseignant peut retirer un malus appliqué", function () {
    [
        'enseignant' => $enseignant,
        'classe' => $classe,
        'groupe' => $groupe,
        'etudiant' => $etudiant,
        'typeProjet' => $typeProjet,
        'malus' => $malus,
    ] = creerContexteGrilleProjet();

    $url = "/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/grille/malus";

    $this->actingAs($enseignant)->putJson($url, ['malus_id' => $malus->id, 'user_id' => $etudiant->id, 'applique' => true]);

    $this->actingAs($enseignant)
        ->putJson($url, ['malus_id' => $malus->id, 'user_id' => $etudiant->id, 'applique' => false])
        ->assertOk();

    $this->assertDatabaseHas('projet_grille_malus', [
        'malus_id' => $malus->id,
        'user_id' => $etudiant->id,
        'applique' => false,
    ]);
});

test('un malus hors de la grille du type de projet est refusé (protection IDOR)', function () {
    [
        'enseignant' => $enseignant,
        'classe' => $classe,
        'groupe' => $groupe,
        'etudiant' => $etudiant,
        'typeProjet' => $typeProjet,
    ] = creerContexteGrilleProjet();

    $autreType = TypeProjet::create([
        'enseignant_id' => $enseignant->id,
        'nom' => 'Autre type',
        'accessible' => false,
    ]);
    $autreGrille = GrilleCorrection::create(['type_projet_id' => $autreType->id, 'nom' => 'Autre grille']);
    $autreMalus = $autreGrille->malus()->create(['label' => 'Malus hors grille', 'deduction' => 5, 'ordre' => 0]);

    $this->actingAs($enseignant)
        ->putJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/grille/malus", [
            'malus_id' => $autreMalus->id,
            'user_id' => $etudiant->id,
            'applique' => true,
        ])
        ->assertUnprocessable();
});

// ─── Calcul note finale grille ────────────────────────────────────────────────

test("noteFinale retourne null si aucune note n'a été saisie", function () {
    ['projet' => $projet, 'etudiant' => $etudiant] = creerContexteGrilleProjet();

    expect(ProjetGrilleNote::noteFinale($projet, $etudiant))->toBeNull();
});

test('noteFinale calcule correctement la contribution pondérée', function () {
    [
        'projet' => $projet,
        'etudiant' => $etudiant,
        'critere1' => $critere1, // pondération 60
        'critere2' => $critere2, // pondération 40
    ] = creerContexteGrilleProjet();

    // critere1 : note 4 → contribution = (4/4) * 60 = 60
    // critere2 : note 2 → contribution = (2/4) * 40 = 20
    // total = 80
    ProjetGrilleNote::create(['projet_id' => $projet->id, 'user_id' => $etudiant->id, 'critere_id' => $critere1->id, 'note' => 4]);
    ProjetGrilleNote::create(['projet_id' => $projet->id, 'user_id' => $etudiant->id, 'critere_id' => $critere2->id, 'note' => 2]);

    expect(ProjetGrilleNote::noteFinale($projet->fresh(), $etudiant))->toBe(80.0);
});

test('noteFinale = 100 quand toutes les notes sont à Excellent et pondérations = 100', function () {
    [
        'projet' => $projet,
        'etudiant' => $etudiant,
        'critere1' => $critere1,
        'critere2' => $critere2,
    ] = creerContexteGrilleProjet();

    ProjetGrilleNote::create(['projet_id' => $projet->id, 'user_id' => $etudiant->id, 'critere_id' => $critere1->id, 'note' => 4]);
    ProjetGrilleNote::create(['projet_id' => $projet->id, 'user_id' => $etudiant->id, 'critere_id' => $critere2->id, 'note' => 4]);

    expect(ProjetGrilleNote::noteFinale($projet->fresh(), $etudiant))->toBe(100.0);
});

test('noteFinale déduit correctement les malus appliqués', function () {
    [
        'projet' => $projet,
        'etudiant' => $etudiant,
        'critere1' => $critere1, // pondération 60
        'critere2' => $critere2, // pondération 40
        'malus' => $malus,    // déduction 3
    ] = creerContexteGrilleProjet();

    // base = (4/4)*60 + (4/4)*40 = 100, malus = 3 → finale = 97
    ProjetGrilleNote::create(['projet_id' => $projet->id, 'user_id' => $etudiant->id, 'critere_id' => $critere1->id, 'note' => 4]);
    ProjetGrilleNote::create(['projet_id' => $projet->id, 'user_id' => $etudiant->id, 'critere_id' => $critere2->id, 'note' => 4]);

    ProjetGrilleMalus::create([
        'projet_id' => $projet->id,
        'user_id' => $etudiant->id,
        'malus_id' => $malus->id,
        'applique' => true,
    ]);

    expect(ProjetGrilleNote::noteFinale($projet->fresh(), $etudiant))->toBe(97.0);
});

test('noteFinale est planché à 0 (ne peut pas être négative)', function () {
    [
        'projet' => $projet,
        'etudiant' => $etudiant,
        'critere1' => $critere1, // pondération 60
        'malus' => $malus,    // déduction 3
    ] = creerContexteGrilleProjet();

    // Seul critere1 noté à 0 → base = 0, malus = 3 → max(0, -3) = 0
    ProjetGrilleNote::create(['projet_id' => $projet->id, 'user_id' => $etudiant->id, 'critere_id' => $critere1->id, 'note' => 0]);

    ProjetGrilleMalus::create([
        'projet_id' => $projet->id,
        'user_id' => $etudiant->id,
        'malus_id' => $malus->id,
        'applique' => true,
    ]);

    expect(ProjetGrilleNote::noteFinale($projet->fresh(), $etudiant))->toBe(0.0);
});

test('un malus non appliqué (applique = false) ne décompte pas', function () {
    [
        'projet' => $projet,
        'etudiant' => $etudiant,
        'critere1' => $critere1,
        'critere2' => $critere2,
        'malus' => $malus,
    ] = creerContexteGrilleProjet();

    ProjetGrilleNote::create(['projet_id' => $projet->id, 'user_id' => $etudiant->id, 'critere_id' => $critere1->id, 'note' => 4]);
    ProjetGrilleNote::create(['projet_id' => $projet->id, 'user_id' => $etudiant->id, 'critere_id' => $critere2->id, 'note' => 4]);

    // Malus enregistré mais non appliqué
    ProjetGrilleMalus::create([
        'projet_id' => $projet->id,
        'user_id' => $etudiant->id,
        'malus_id' => $malus->id,
        'applique' => false,
    ]);

    expect(ProjetGrilleNote::noteFinale($projet->fresh(), $etudiant))->toBe(100.0);
});
