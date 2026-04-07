<?php

use App\Models\Classe;
use App\Models\Groupe;
use App\Models\ProjetRecherche;
use App\Models\ProjetSectionContenu;
use App\Models\TypeProjet;
use App\Models\TypeProjetSection;
use App\Models\User;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Crée un scénario complet : enseignant, classe, groupe, étudiant membre,
 * type de projet avec une section, et un projet associé.
 *
 * @return array{enseignant: User, etudiant: User, classe: Classe, groupe: Groupe, typeProjet: TypeProjet, projet: ProjetRecherche, section: TypeProjetSection}
 */
function creerScenarioSection(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $etudiant = User::factory()->create(['role' => 'etudiant']);

    $classe = Classe::create([
        'nom_cours' => 'Hist', 'description' => 'T', 'code' => '330-T1',
        'groupe' => '01', 'enseignant_id' => $enseignant->id,
    ]);
    $classe->etudiants()->attach($etudiant->id);

    $groupe = Groupe::create(['classe_id' => $classe->id, 'created_by' => $etudiant->id]);
    $groupe->membres()->attach($etudiant->id);

    $typeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id,
        'nom' => 'Type test',
        'accessible' => true,
    ]);
    $section = TypeProjetSection::create([
        'type_projet_id' => $typeProjet->id,
        'label' => 'Introduction',
        'ordre' => 1,
    ]);

    $projet = ProjetRecherche::create([
        'groupe_id' => $groupe->id,
        'type_projet_id' => $typeProjet->id,
    ]);

    return compact('enseignant', 'etudiant', 'classe', 'groupe', 'typeProjet', 'projet', 'section');
}

// ─── updateSectionContenu ─────────────────────────────────────────────────────

test("un étudiant membre peut sauvegarder le contenu d'une section", function () {
    ['etudiant' => $etudiant, 'classe' => $classe, 'groupe' => $groupe, 'typeProjet' => $typeProjet, 'projet' => $projet, 'section' => $section] = creerScenarioSection();

    $this->actingAs($etudiant)
        ->putJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/sections/{$section->id}", [
            'contenu' => '<p>Texte de la section</p>',
        ])
        ->assertOk()
        ->assertJsonFragment(['message' => 'saved']);

    $this->assertDatabaseHas('projet_section_contenus', [
        'projet_id' => $projet->id,
        'section_id' => $section->id,
        'contenu' => '<p>Texte de la section</p>',
    ]);
});

test('un deuxième PUT sur la même section fait un upsert (pas de doublon)', function () {
    ['etudiant' => $etudiant, 'classe' => $classe, 'groupe' => $groupe, 'typeProjet' => $typeProjet, 'projet' => $projet, 'section' => $section] = creerScenarioSection();

    $this->actingAs($etudiant)
        ->putJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/sections/{$section->id}", [
            'contenu' => '<p>Version 1</p>',
        ]);

    $this->actingAs($etudiant)
        ->putJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/sections/{$section->id}", [
            'contenu' => '<p>Version 2</p>',
        ])
        ->assertOk();

    $this->assertDatabaseCount('projet_section_contenus', 1);
    $this->assertDatabaseHas('projet_section_contenus', ['contenu' => '<p>Version 2</p>']);
});

test("un non-membre ne peut pas sauvegarder le contenu d'une section (403)", function () {
    ['classe' => $classe, 'groupe' => $groupe, 'typeProjet' => $typeProjet, 'section' => $section] = creerScenarioSection();

    $autreEtudiant = User::factory()->create(['role' => 'etudiant']);
    $classe->etudiants()->attach($autreEtudiant->id);

    $this->actingAs($autreEtudiant)
        ->putJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/sections/{$section->id}", [
            'contenu' => '<p>Piratage</p>',
        ])
        ->assertForbidden();
});

test("impossible de modifier la section d'un autre type de projet (IDOR)", function () {
    ['etudiant' => $etudiant, 'classe' => $classe, 'groupe' => $groupe, 'typeProjet' => $typeProjet] = creerScenarioSection();

    // Section appartenant à un autre type de projet
    $autreType = TypeProjet::create([
        'enseignant_id' => User::factory()->create(['role' => 'enseignant'])->id,
        'nom' => 'Autre type',
        'accessible' => true,
    ]);
    $autreSection = TypeProjetSection::create([
        'type_projet_id' => $autreType->id,
        'label' => 'Section étrangère',
        'ordre' => 1,
    ]);

    $this->actingAs($etudiant)
        ->putJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/sections/{$autreSection->id}", [
            'contenu' => '<p>Injection</p>',
        ])
        ->assertNotFound();
});

test('un étudiant ne peut pas modifier une section si le projet est verrouillé', function () {
    ['etudiant' => $etudiant, 'classe' => $classe, 'groupe' => $groupe, 'typeProjet' => $typeProjet, 'projet' => $projet, 'section' => $section] = creerScenarioSection();

    $projet->update(['verrouille' => true]);

    $this->actingAs($etudiant)
        ->putJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/sections/{$section->id}", [
            'contenu' => '<p>Bloqué</p>',
        ])
        ->assertForbidden();
});

// ─── Sections visibles dans show() ───────────────────────────────────────────

test('le show du projet contient les sections avec leur contenu', function () {
    ['etudiant' => $etudiant, 'classe' => $classe, 'groupe' => $groupe, 'typeProjet' => $typeProjet, 'projet' => $projet, 'section' => $section] = creerScenarioSection();

    ProjetSectionContenu::create([
        'projet_id' => $projet->id,
        'section_id' => $section->id,
        'contenu' => '<p>Contenu test</p>',
    ]);

    $this->actingAs($etudiant)
        ->get("/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/edit")
        ->assertInertia(fn ($page) => $page
            ->component('Projets/Show')
            ->has('sections', 1)
            ->where('sections.0.label', 'Introduction')
            ->where('sections.0.contenu', '<p>Contenu test</p>')
        );
});

test("le show retourne un tableau sections vide si le type de projet n'a aucune section", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $etudiant = User::factory()->create(['role' => 'etudiant']);

    $classe = Classe::create([
        'nom_cours' => 'Hist', 'description' => 'T', 'code' => '330-T2',
        'groupe' => '01', 'enseignant_id' => $enseignant->id,
    ]);
    $classe->etudiants()->attach($etudiant->id);

    $groupe = Groupe::create(['classe_id' => $classe->id, 'created_by' => $etudiant->id]);
    $groupe->membres()->attach($etudiant->id);

    // TypeProjet sans aucune section
    $typeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id,
        'nom' => 'Type sans sections',
        'accessible' => true,
    ]);

    $this->actingAs($etudiant)
        ->get("/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/edit")
        ->assertInertia(fn ($page) => $page
            ->component('Projets/Show')
            ->where('sections', [])
        );
});
