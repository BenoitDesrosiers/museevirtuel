<?php

use App\Models\Classe;
use App\Models\Cours;
use App\Models\EntrevueConcept;
use App\Models\EntrevueLigne;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use App\Models\TypeProjetSection;
use App\Models\User;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Crée un scénario : enseignant, classe, groupe, étudiant membre,
 * typeProjet avec une section de type 'entrevue', et un projet.
 *
 * @return array{enseignant: User, etudiant: User, cours: Cours, classe: Classe, typeProjet: TypeProjet, section: TypeProjetSection, projet: ProjetRecherche}
 */
function creerScenarioEntrevue(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $etudiant = User::factory()->create(['role' => 'etudiant']);

    $cours = Cours::create([
        'nom_cours' => 'Histoire',
        'description' => 'T',
        'code' => '330-ENT',
        'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);
    $cours->etudiants()->attach($etudiant->id);

    $classe = Classe::create(['cours_id' => $cours->id, 'created_by' => $etudiant->id]);
    $classe->membres()->attach($etudiant->id);

    $typeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id,
        'nom' => "Schéma d'entrevue",
        'accessible' => true,
    ]);

    $section = TypeProjetSection::create([
        'type_projet_id' => $typeProjet->id,
        'label' => 'Concepts',
        'type' => 'entrevue',
        'ordre' => 1,
    ]);

    $projet = ProjetRecherche::create([
        'classe_id' => $classe->id,
        'type_projet_id' => $typeProjet->id,
    ]);

    return compact('enseignant', 'etudiant', 'cours', 'classe', 'typeProjet', 'section', 'projet');
}

/**
 * Retourne l'URL de base pour les routes concepts.
 */
function urlConcepts(Cours $cours, Classe $classe, TypeProjet $typeProjet, TypeProjetSection $section): string
{
    return "/cours/{$cours->id}/classes/{$classe->id}/projets/{$typeProjet->id}/sections/{$section->id}/concepts";
}

// ─── store() — Créer un concept ───────────────────────────────────────────────

test('un membre peut créer un concept dans une section entrevue', function () {
    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'typeProjet' => $typeProjet, 'section' => $section] = creerScenarioEntrevue();

    $response = $this->actingAs($etudiant)
        ->postJson(urlConcepts($cours, $classe, $typeProjet, $section), ['label' => 'Pratique religieuse']);

    $response->assertCreated()
        ->assertJsonFragment(['message' => 'created'])
        ->assertJsonPath('concept.label', 'Pratique religieuse')
        ->assertJsonPath('concept.ordre', 1);

    $this->assertDatabaseHas('entrevue_concepts', [
        'label' => 'Pratique religieuse',
        'section_id' => $section->id,
    ]);
});

test("l'ordre s'incrémente automatiquement", function () {
    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'typeProjet' => $typeProjet, 'section' => $section, 'projet' => $projet] = creerScenarioEntrevue();

    EntrevueConcept::create(['projet_id' => $projet->id, 'section_id' => $section->id, 'label' => 'Premier', 'ordre' => 1]);

    $response = $this->actingAs($etudiant)
        ->postJson(urlConcepts($cours, $classe, $typeProjet, $section), ['label' => 'Deuxième']);

    $response->assertCreated()
        ->assertJsonPath('concept.ordre', 2);
});

test('un non-membre ne peut pas créer un concept (403)', function () {
    ['cours' => $cours, 'classe' => $classe, 'typeProjet' => $typeProjet, 'section' => $section] = creerScenarioEntrevue();

    $etranger = User::factory()->create(['role' => 'etudiant']);
    $cours->etudiants()->attach($etranger->id);

    $this->actingAs($etranger)
        ->postJson(urlConcepts($cours, $classe, $typeProjet, $section), ['label' => 'Tentative'])
        ->assertForbidden();
});

test('une section de type texte refuse la création de concepts (422)', function () {
    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'typeProjet' => $typeProjet] = creerScenarioEntrevue();

    $sectionTexte = TypeProjetSection::create([
        'type_projet_id' => $typeProjet->id,
        'label' => 'Introduction',
        'type' => 'texte',
        'ordre' => 2,
    ]);

    $this->actingAs($etudiant)
        ->postJson(urlConcepts($cours, $classe, $typeProjet, $sectionTexte), ['label' => 'Tentative'])
        ->assertUnprocessable();
});

test('un document verrouillé refuse la création de concepts (403)', function () {
    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'typeProjet' => $typeProjet, 'section' => $section, 'projet' => $projet] = creerScenarioEntrevue();

    $projet->update(['verrouille' => true]);

    $this->actingAs($etudiant)
        ->postJson(urlConcepts($cours, $classe, $typeProjet, $section), ['label' => 'Tentative'])
        ->assertForbidden();
});

// ─── update() — Mettre à jour le label ────────────────────────────────────────

test("un membre peut mettre à jour le label d'un concept", function () {
    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'typeProjet' => $typeProjet, 'section' => $section, 'projet' => $projet] = creerScenarioEntrevue();

    $concept = EntrevueConcept::create(['projet_id' => $projet->id, 'section_id' => $section->id, 'label' => 'Ancien label', 'ordre' => 1]);

    $this->actingAs($etudiant)
        ->patchJson(urlConcepts($cours, $classe, $typeProjet, $section)."/{$concept->id}", ['label' => 'Nouveau label'])
        ->assertOk()
        ->assertJsonFragment(['message' => 'saved']);

    $this->assertDatabaseHas('entrevue_concepts', ['id' => $concept->id, 'label' => 'Nouveau label']);
});

test("un concept d'une autre section est refusé à la mise à jour (404)", function () {
    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'typeProjet' => $typeProjet, 'section' => $section, 'projet' => $projet] = creerScenarioEntrevue();

    $autreSection = TypeProjetSection::create([
        'type_projet_id' => $typeProjet->id,
        'label' => 'Autre',
        'type' => 'entrevue',
        'ordre' => 2,
    ]);

    $conceptAutre = EntrevueConcept::create(['projet_id' => $projet->id, 'section_id' => $autreSection->id, 'label' => 'Autre', 'ordre' => 1]);

    // Tente de modifier un concept qui n'appartient pas à la section passée en URL
    $this->actingAs($etudiant)
        ->patchJson(urlConcepts($cours, $classe, $typeProjet, $section)."/{$conceptAutre->id}", ['label' => 'Hack'])
        ->assertNotFound();
});

// ─── destroy() — Supprimer un concept ─────────────────────────────────────────

test('un membre peut supprimer un concept', function () {
    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'typeProjet' => $typeProjet, 'section' => $section, 'projet' => $projet] = creerScenarioEntrevue();

    $concept = EntrevueConcept::create(['projet_id' => $projet->id, 'section_id' => $section->id, 'label' => 'À supprimer', 'ordre' => 1]);

    $this->actingAs($etudiant)
        ->deleteJson(urlConcepts($cours, $classe, $typeProjet, $section)."/{$concept->id}")
        ->assertOk()
        ->assertJsonFragment(['message' => 'deleted']);

    $this->assertDatabaseMissing('entrevue_concepts', ['id' => $concept->id]);
});

test('supprimer un concept réordonne les suivants', function () {
    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'typeProjet' => $typeProjet, 'section' => $section, 'projet' => $projet] = creerScenarioEntrevue();

    $c1 = EntrevueConcept::create(['projet_id' => $projet->id, 'section_id' => $section->id, 'label' => 'C1', 'ordre' => 1]);
    $c2 = EntrevueConcept::create(['projet_id' => $projet->id, 'section_id' => $section->id, 'label' => 'C2', 'ordre' => 2]);
    $c3 = EntrevueConcept::create(['projet_id' => $projet->id, 'section_id' => $section->id, 'label' => 'C3', 'ordre' => 3]);

    $this->actingAs($etudiant)
        ->deleteJson(urlConcepts($cours, $classe, $typeProjet, $section)."/{$c1->id}");

    expect($c2->fresh()->ordre)->toBe(1)
        ->and($c3->fresh()->ordre)->toBe(2);
});

// ─── reorder() ────────────────────────────────────────────────────────────────

test('un membre peut réordonner les concepts', function () {
    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'typeProjet' => $typeProjet, 'section' => $section, 'projet' => $projet] = creerScenarioEntrevue();

    $c1 = EntrevueConcept::create(['projet_id' => $projet->id, 'section_id' => $section->id, 'label' => 'C1', 'ordre' => 1]);
    $c2 = EntrevueConcept::create(['projet_id' => $projet->id, 'section_id' => $section->id, 'label' => 'C2', 'ordre' => 2]);

    $this->actingAs($etudiant)
        ->patchJson(urlConcepts($cours, $classe, $typeProjet, $section).'/reorder', [
            'ordre' => [$c2->id, $c1->id],
        ])
        ->assertOk()
        ->assertJsonFragment(['message' => 'reordered']);

    expect($c2->fresh()->ordre)->toBe(1)
        ->and($c1->fresh()->ordre)->toBe(2);
});

// ─── storeLigne() ─────────────────────────────────────────────────────────────

test('un membre peut ajouter une ligne à un concept', function () {
    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'typeProjet' => $typeProjet, 'section' => $section, 'projet' => $projet] = creerScenarioEntrevue();

    $concept = EntrevueConcept::create(['projet_id' => $projet->id, 'section_id' => $section->id, 'label' => 'Concept', 'ordre' => 1]);

    $response = $this->actingAs($etudiant)
        ->postJson(urlConcepts($cours, $classe, $typeProjet, $section)."/{$concept->id}/lignes");

    $response->assertCreated()
        ->assertJsonFragment(['message' => 'created'])
        ->assertJsonPath('ligne.ordre', 1);

    $this->assertDatabaseHas('entrevue_lignes', ['concept_id' => $concept->id, 'ordre' => 1]);
});

// ─── updateLigne() ────────────────────────────────────────────────────────────

test('un membre peut mettre à jour une ligne (dimension/indicateur/questions)', function () {
    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'typeProjet' => $typeProjet, 'section' => $section, 'projet' => $projet] = creerScenarioEntrevue();

    $concept = EntrevueConcept::create(['projet_id' => $projet->id, 'section_id' => $section->id, 'label' => 'Concept', 'ordre' => 1]);
    $ligne = EntrevueLigne::create(['concept_id' => $concept->id, 'ordre' => 1, 'questions' => []]);

    $this->actingAs($etudiant)
        ->patchJson(
            urlConcepts($cours, $classe, $typeProjet, $section)."/{$concept->id}/lignes/{$ligne->id}",
            [
                'dimension' => 'Fréquence de pratique',
                'indicateur' => 'Présence à la messe',
                'questions' => ['Question 1 ?', 'Question 2 ?'],
            ]
        )
        ->assertOk()
        ->assertJsonFragment(['message' => 'saved']);

    $ligneUpdated = $ligne->fresh();
    expect($ligneUpdated->dimension)->toBe('Fréquence de pratique')
        ->and($ligneUpdated->indicateur)->toBe('Présence à la messe')
        ->and($ligneUpdated->questions)->toEqual(['Question 1 ?', 'Question 2 ?']);
});

test('une ligne appartenant à un autre concept est refusée (404)', function () {
    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'typeProjet' => $typeProjet, 'section' => $section, 'projet' => $projet] = creerScenarioEntrevue();

    $concept1 = EntrevueConcept::create(['projet_id' => $projet->id, 'section_id' => $section->id, 'label' => 'C1', 'ordre' => 1]);
    $concept2 = EntrevueConcept::create(['projet_id' => $projet->id, 'section_id' => $section->id, 'label' => 'C2', 'ordre' => 2]);
    $ligne = EntrevueLigne::create(['concept_id' => $concept2->id, 'ordre' => 1, 'questions' => []]);

    // Tente de mettre à jour la ligne de concept2 via les routes de concept1
    $this->actingAs($etudiant)
        ->patchJson(
            urlConcepts($cours, $classe, $typeProjet, $section)."/{$concept1->id}/lignes/{$ligne->id}",
            ['dimension' => 'Hack']
        )
        ->assertNotFound();
});

// ─── destroyLigne() ───────────────────────────────────────────────────────────

test('un membre peut supprimer une ligne', function () {
    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'typeProjet' => $typeProjet, 'section' => $section, 'projet' => $projet] = creerScenarioEntrevue();

    $concept = EntrevueConcept::create(['projet_id' => $projet->id, 'section_id' => $section->id, 'label' => 'C', 'ordre' => 1]);
    $ligne = EntrevueLigne::create(['concept_id' => $concept->id, 'ordre' => 1, 'questions' => []]);

    $this->actingAs($etudiant)
        ->deleteJson(urlConcepts($cours, $classe, $typeProjet, $section)."/{$concept->id}/lignes/{$ligne->id}")
        ->assertOk()
        ->assertJsonFragment(['message' => 'deleted']);

    $this->assertDatabaseMissing('entrevue_lignes', ['id' => $ligne->id]);
});

test('supprimer une ligne réordonne les suivantes', function () {
    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'typeProjet' => $typeProjet, 'section' => $section, 'projet' => $projet] = creerScenarioEntrevue();

    $concept = EntrevueConcept::create(['projet_id' => $projet->id, 'section_id' => $section->id, 'label' => 'C', 'ordre' => 1]);
    $l1 = EntrevueLigne::create(['concept_id' => $concept->id, 'ordre' => 1, 'questions' => []]);
    $l2 = EntrevueLigne::create(['concept_id' => $concept->id, 'ordre' => 2, 'questions' => []]);
    $l3 = EntrevueLigne::create(['concept_id' => $concept->id, 'ordre' => 3, 'questions' => []]);

    $this->actingAs($etudiant)
        ->deleteJson(urlConcepts($cours, $classe, $typeProjet, $section)."/{$concept->id}/lignes/{$l1->id}");

    expect($l2->fresh()->ordre)->toBe(1)
        ->and($l3->fresh()->ordre)->toBe(2);
});

// ─── Sécurité IDOR — TypeProjet d'un autre enseignant ─────────────────────────

test("un type de projet d'un autre enseignant est refusé (404)", function () {
    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'section' => $section] = creerScenarioEntrevue();

    $autreEnseignant = User::factory()->create(['role' => 'enseignant']);
    $autreTypeProjet = TypeProjet::create([
        'enseignant_id' => $autreEnseignant->id,
        'nom' => 'Autre type',
        'accessible' => true,
    ]);

    $this->actingAs($etudiant)
        ->postJson(
            "/cours/{$cours->id}/classes/{$classe->id}/projets/{$autreTypeProjet->id}/sections/{$section->id}/concepts",
            ['label' => 'IDOR']
        )
        ->assertNotFound();
});
