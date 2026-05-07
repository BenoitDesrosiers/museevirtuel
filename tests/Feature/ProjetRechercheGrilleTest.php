<?php

use App\Models\Classe;
use App\Models\Cours;
use App\Models\GrilleCorrection;
use App\Models\GrilleCritere;
use App\Models\GrilleMalus;
use App\Models\Groupe;
use App\Models\ProjetAnnotation;
use App\Models\ProjetGrilleMalus;
use App\Models\ProjetGrilleNote;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use App\Models\TypeProjetSection;
use App\Models\User;

// ─── Helper ───────────────────────────────────────────────────────────────────

/**
 * Crée un contexte complet : enseignant, section de cours, groupe, étudiant membre,
 * type de projet avec grille, et projet.
 *
 * @return array{
 *     enseignant: User,
 *     cours: Cours,
 *     classeSection: Classe,
 *     classe: Groupe,
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

    $cours = Cours::create([
        'nom_cours' => 'Histoire',
        'description' => 'Test',
        'code' => '330-G1',
        'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);

    $etudiant = User::factory()->create(['role' => 'etudiant']);

    $classeSection = Classe::create(['cours_id' => $cours->id]);
    $classeSection->etudiants()->attach($etudiant->id);

    $classe = Groupe::create([
        'classe_id' => $classeSection->id,
        'created_by' => $etudiant->id,
    ]);
    $classe->membres()->attach($etudiant->id);

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
        'groupe_id' => $classe->id,
        'type_projet_id' => $typeProjet->id,
    ]);

    return compact('enseignant', 'cours', 'classeSection', 'classe', 'etudiant', 'projet', 'typeProjet', 'grille', 'critere1', 'critere2')
        + ['malus' => $malus];
}

// ─── upsertNoteGrille ─────────────────────────────────────────────────────────

test("l'enseignant peut sauvegarder une note pour un critère de la grille", function () {
    [
        'enseignant' => $enseignant,
        'classeSection' => $cs,
        'classe' => $classe,
        'cours' => $cours,
        'etudiant' => $etudiant,
        'projet' => $projet,
        'typeProjet' => $typeProjet,
        'critere1' => $critere1,
    ] = creerContexteGrilleProjet();

    $this->actingAs($enseignant)
        ->putJson("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/grille/notes", [
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
        'classeSection' => $cs,
        'classe' => $classe,
        'cours' => $cours,
        'etudiant' => $etudiant,
        'projet' => $projet,
        'typeProjet' => $typeProjet,
        'critere1' => $critere1,
    ] = creerContexteGrilleProjet();

    $url = "/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/grille/notes";

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
        'classeSection' => $cs,
        'classe' => $classe,
        'cours' => $cours,
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
        ->putJson("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/grille/notes", [
            'critere_id' => $autreCritere->id,
            'note' => 4,
            'user_id' => $etudiant->id,
        ])
        ->assertUnprocessable();
});

test('noter un étudiant hors du groupe est refusé (protection IDOR)', function () {
    [
        'enseignant' => $enseignant,
        'classeSection' => $cs,
        'classe' => $classe,
        'cours' => $cours,
        'typeProjet' => $typeProjet,
        'critere1' => $critere1,
    ] = creerContexteGrilleProjet();

    $etudiantHors = User::factory()->create(['role' => 'etudiant']);

    $this->actingAs($enseignant)
        ->putJson("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/grille/notes", [
            'critere_id' => $critere1->id,
            'note' => 3,
            'user_id' => $etudiantHors->id,
        ])
        ->assertStatus(422);
});

test('un étudiant ne peut pas sauvegarder une note grille', function () {
    [
        'etudiant' => $etudiant,
        'classeSection' => $cs,
        'classe' => $classe,
        'cours' => $cours,
        'typeProjet' => $typeProjet,
        'critere1' => $critere1,
    ] = creerContexteGrilleProjet();

    $this->actingAs($etudiant)
        ->putJson("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/grille/notes", [
            'critere_id' => $critere1->id,
            'note' => 3,
            'user_id' => $etudiant->id,
        ])
        ->assertForbidden();
});

it('accepte uniquement les notes 0, 2, 3 et 4 pour une note grille', function (int $note) {
    [
        'enseignant' => $enseignant,
        'classeSection' => $cs,
        'classe' => $classe,
        'cours' => $cours,
        'etudiant' => $etudiant,
        'typeProjet' => $typeProjet,
        'critere1' => $critere1,
    ] = creerContexteGrilleProjet();

    $this->actingAs($enseignant)
        ->putJson("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/grille/notes", [
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
        'classeSection' => $cs,
        'classe' => $classe,
        'cours' => $cours,
        'etudiant' => $etudiant,
        'projet' => $projet,
        'typeProjet' => $typeProjet,
        'malus' => $malus,
    ] = creerContexteGrilleProjet();

    $this->actingAs($enseignant)
        ->putJson("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/grille/malus", [
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
        'classeSection' => $cs,
        'classe' => $classe,
        'cours' => $cours,
        'etudiant' => $etudiant,
        'typeProjet' => $typeProjet,
        'malus' => $malus,
    ] = creerContexteGrilleProjet();

    $url = "/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/grille/malus";

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
        'classeSection' => $cs,
        'classe' => $classe,
        'cours' => $cours,
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
        ->putJson("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/grille/malus", [
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

// ─── Points malus annotations ─────────────────────────────────────────────────

it('noteFinale déduit les points_malus des annotations de correction', function () {
    [
        'projet' => $projet,
        'etudiant' => $etudiant,
        'critere1' => $critere1, // pondération 60
        'critere2' => $critere2, // pondération 40
    ] = creerContexteGrilleProjet();

    // base = (4/4)*60 + (4/4)*40 = 100
    ProjetGrilleNote::create(['projet_id' => $projet->id, 'user_id' => $etudiant->id, 'critere_id' => $critere1->id, 'note' => 4]);
    ProjetGrilleNote::create(['projet_id' => $projet->id, 'user_id' => $etudiant->id, 'critere_id' => $critere2->id, 'note' => 4]);

    ProjetAnnotation::create([
        'projet_id' => $projet->id,
        'champ' => 'page_titre_contenu',
        'commentaire_id' => 'aaa-bbb-ccc',
        'contenu' => 'Erreur de méthode',
        'type' => 'correction',
        'user_id' => $etudiant->id,
        'cible_user_id' => $etudiant->id,
        'points_malus' => 5,
    ]);

    // 100 - 5 = 95
    expect(ProjetGrilleNote::noteFinale($projet->fresh(), $etudiant))->toBe(95.0);
});

it('noteFinale ignore les annotations de correction sans points_malus', function () {
    [
        'projet' => $projet,
        'etudiant' => $etudiant,
        'critere1' => $critere1,
        'critere2' => $critere2,
    ] = creerContexteGrilleProjet();

    ProjetGrilleNote::create(['projet_id' => $projet->id, 'user_id' => $etudiant->id, 'critere_id' => $critere1->id, 'note' => 4]);
    ProjetGrilleNote::create(['projet_id' => $projet->id, 'user_id' => $etudiant->id, 'critere_id' => $critere2->id, 'note' => 4]);

    ProjetAnnotation::create([
        'projet_id' => $projet->id,
        'champ' => 'page_titre_contenu',
        'commentaire_id' => 'aaa-bbb-001',
        'contenu' => 'Commentaire sans déduction',
        'type' => 'correction',
        'user_id' => $etudiant->id,
        'cible_user_id' => $etudiant->id,
        'points_malus' => null,
    ]);

    expect(ProjetGrilleNote::noteFinale($projet->fresh(), $etudiant))->toBe(100.0);
});

it('noteFinale ignore les annotations de type commentaire même avec points_malus', function () {
    [
        'projet' => $projet,
        'etudiant' => $etudiant,
        'critere1' => $critere1,
        'critere2' => $critere2,
    ] = creerContexteGrilleProjet();

    ProjetGrilleNote::create(['projet_id' => $projet->id, 'user_id' => $etudiant->id, 'critere_id' => $critere1->id, 'note' => 4]);
    ProjetGrilleNote::create(['projet_id' => $projet->id, 'user_id' => $etudiant->id, 'critere_id' => $critere2->id, 'note' => 4]);

    // type='commentaire' : les points_malus ne doivent jamais être déduits
    ProjetAnnotation::create([
        'projet_id' => $projet->id,
        'champ' => 'introduction_amener',
        'commentaire_id' => 'aaa-bbb-002',
        'contenu' => 'Bon travail',
        'type' => 'commentaire',
        'user_id' => $etudiant->id,
        'cible_user_id' => $etudiant->id,
        'points_malus' => 10,
    ]);

    expect(ProjetGrilleNote::noteFinale($projet->fresh(), $etudiant))->toBe(100.0);
});

test('upsertAnnotation retourne noteFinaleGrilleParEtudiant quand correction avec points_malus', function () {
    [
        'enseignant' => $enseignant,
        'cours' => $cours,
        'classeSection' => $cs,
        'classe' => $classe,
        'etudiant' => $etudiant,
        'typeProjet' => $typeProjet,
        'critere1' => $critere1,
        'critere2' => $critere2,
        'projet' => $projet,
    ] = creerContexteGrilleProjet();

    // base = (4/4)*60 + (4/4)*40 = 100
    ProjetGrilleNote::create(['projet_id' => $projet->id, 'user_id' => $etudiant->id, 'critere_id' => $critere1->id, 'note' => 4]);
    ProjetGrilleNote::create(['projet_id' => $projet->id, 'user_id' => $etudiant->id, 'critere_id' => $critere2->id, 'note' => 4]);

    // Une section valide est nécessaire pour que mettreAJourChampHtml respecte la FK section_id
    $section = TypeProjetSection::create([
        'type_projet_id' => $typeProjet->id,
        'label' => 'Section test',
        'ordre' => 0,
        'type' => 'texte',
    ]);
    $champ = "section_{$section->id}";
    $commentaireId = 'test-uuid-1234';
    $html = '<p><mark data-comment-id="'.$commentaireId.'" data-annotation-type="correction">texte annoté</mark></p>';

    $this->actingAs($enseignant)
        ->putJson("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/annotations", [
            'champ' => $champ,
            'commentaire_id' => $commentaireId,
            'contenu' => 'Erreur de méthode',
            'html' => $html,
            'type' => 'correction',
            'cible_user_id' => $etudiant->id,
            'points_malus' => 8,
        ])
        ->assertOk()
        ->assertJsonStructure(['noteFinaleGrilleParEtudiant'])
        ->assertJsonPath("noteFinaleGrilleParEtudiant.{$etudiant->id}", 92); // 100 - 8
});

it('noteFinale déduit les annotations de correction ciblant tous les étudiants (cible_user_id = null)', function () {
    [
        'projet' => $projet,
        'etudiant' => $etudiant,
        'critere1' => $critere1,
        'critere2' => $critere2,
    ] = creerContexteGrilleProjet();

    ProjetGrilleNote::create(['projet_id' => $projet->id, 'user_id' => $etudiant->id, 'critere_id' => $critere1->id, 'note' => 4]);
    ProjetGrilleNote::create(['projet_id' => $projet->id, 'user_id' => $etudiant->id, 'critere_id' => $critere2->id, 'note' => 4]);

    // cible_user_id = null → s'applique à tous les étudiants
    ProjetAnnotation::create([
        'projet_id' => $projet->id,
        'champ' => 'introduction_amener',
        'commentaire_id' => 'aaa-bbb-003',
        'contenu' => 'Format incorrect pour tous',
        'type' => 'correction',
        'user_id' => $etudiant->id,
        'cible_user_id' => null,
        'points_malus' => 7,
    ]);

    // 100 - 7 = 93
    expect(ProjetGrilleNote::noteFinale($projet->fresh(), $etudiant))->toBe(93.0);
});
