<?php

use App\Models\Classe;
use App\Models\Groupe;
use App\Models\ProjetConclusion;
use App\Models\ProjetRecherche;
use App\Models\ProjetSectionParagraphe;
use App\Models\TypeProjet;
use App\Models\TypeProjetSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helper ───────────────────────────────────────────────────────────────────

/**
 * Crée un scénario avec une section de type 'paragraphes'.
 *
 * @return array{enseignant: User, etudiant: User, classe: Classe, groupe: Groupe, typeProjet: TypeProjet, projet: ProjetRecherche, section: TypeProjetSection}
 */
function creerScenarioParagraphe(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $etudiant = User::factory()->create(['role' => 'etudiant']);

    $classe = Classe::create([
        'nom_cours' => 'Hist', 'description' => 'T', 'code' => '330-P1',
        'groupe' => '01', 'enseignant_id' => $enseignant->id,
    ]);
    $classe->etudiants()->attach($etudiant->id);

    $groupe = Groupe::create(['classe_id' => $classe->id, 'created_by' => $etudiant->id]);
    $groupe->membres()->attach($etudiant->id);

    $typeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id,
        'nom' => 'Type paragraphes',
        'accessible' => true,
    ]);

    $section = TypeProjetSection::create([
        'type_projet_id' => $typeProjet->id,
        'label' => 'Développement',
        'ordre' => 1,
        'type' => 'paragraphes',
    ]);

    $projet = ProjetRecherche::create([
        'groupe_id' => $groupe->id,
        'type_projet_id' => $typeProjet->id,
    ]);

    return compact('enseignant', 'etudiant', 'classe', 'groupe', 'typeProjet', 'projet', 'section');
}

// ─── storeSectionParagraphe ───────────────────────────────────────────────────

test('un membre peut créer un paragraphe dans une section de type paragraphes', function () {
    ['etudiant' => $etudiant, 'classe' => $classe, 'groupe' => $groupe, 'typeProjet' => $typeProjet, 'projet' => $projet, 'section' => $section] = creerScenarioParagraphe();

    $this->actingAs($etudiant)
        ->postJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/sections/{$section->id}/paragraphes")
        ->assertCreated()
        ->assertJsonFragment(['message' => 'created'])
        ->assertJsonPath('paragraphe.ordre', 1);

    $this->assertDatabaseHas('projet_section_paragraphes', [
        'projet_id' => $projet->id,
        'section_id' => $section->id,
        'ordre' => 1,
    ]);
});

test('le premier paragraphe a l\'ordre 1', function () {
    ['etudiant' => $etudiant, 'classe' => $classe, 'groupe' => $groupe, 'typeProjet' => $typeProjet, 'section' => $section] = creerScenarioParagraphe();

    $response = $this->actingAs($etudiant)
        ->postJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/sections/{$section->id}/paragraphes")
        ->assertCreated();

    expect($response->json('paragraphe.ordre'))->toBe(1);
});

test('un deuxième paragraphe a l\'ordre 2', function () {
    ['etudiant' => $etudiant, 'classe' => $classe, 'groupe' => $groupe, 'typeProjet' => $typeProjet, 'projet' => $projet, 'section' => $section] = creerScenarioParagraphe();

    ProjetSectionParagraphe::create([
        'projet_id' => $projet->id,
        'section_id' => $section->id,
        'ordre' => 1,
    ]);

    $response = $this->actingAs($etudiant)
        ->postJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/sections/{$section->id}/paragraphes")
        ->assertCreated();

    expect($response->json('paragraphe.ordre'))->toBe(2);
});

test('un non-membre ne peut pas créer un paragraphe (403)', function () {
    ['classe' => $classe, 'groupe' => $groupe, 'typeProjet' => $typeProjet, 'section' => $section] = creerScenarioParagraphe();

    $autre = User::factory()->create(['role' => 'etudiant']);
    $classe->etudiants()->attach($autre->id);

    $this->actingAs($autre)
        ->postJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/sections/{$section->id}/paragraphes")
        ->assertForbidden();
});

test('une section d\'un autre TypeProjet retourne 404', function () {
    ['etudiant' => $etudiant, 'classe' => $classe, 'groupe' => $groupe, 'typeProjet' => $typeProjet] = creerScenarioParagraphe();

    $autreType = TypeProjet::create([
        'enseignant_id' => User::factory()->create(['role' => 'enseignant'])->id,
        'nom' => 'Autre type', 'accessible' => true,
    ]);
    $autreSection = TypeProjetSection::create([
        'type_projet_id' => $autreType->id,
        'label' => 'Section étrangère',
        'ordre' => 1,
        'type' => 'paragraphes',
    ]);

    $this->actingAs($etudiant)
        ->postJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/sections/{$autreSection->id}/paragraphes")
        ->assertNotFound();
});

test('projet verrouillé interdit la création d\'un paragraphe (403)', function () {
    ['etudiant' => $etudiant, 'classe' => $classe, 'groupe' => $groupe, 'typeProjet' => $typeProjet, 'projet' => $projet, 'section' => $section] = creerScenarioParagraphe();

    $projet->update(['verrouille' => true]);

    $this->actingAs($etudiant)
        ->postJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/sections/{$section->id}/paragraphes")
        ->assertForbidden();
});

// ─── updateSectionParagraphe ──────────────────────────────────────────────────

test('un membre peut modifier le titre et le contenu d\'un paragraphe', function () {
    ['etudiant' => $etudiant, 'classe' => $classe, 'groupe' => $groupe, 'typeProjet' => $typeProjet, 'projet' => $projet, 'section' => $section] = creerScenarioParagraphe();

    $paragraphe = ProjetSectionParagraphe::create([
        'projet_id' => $projet->id,
        'section_id' => $section->id,
        'ordre' => 1,
    ]);

    $this->actingAs($etudiant)
        ->patchJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/sections/{$section->id}/paragraphes/{$paragraphe->id}", [
            'titre' => 'Mon titre',
            'contenu' => '<p>Mon contenu</p>',
        ])
        ->assertOk()
        ->assertJsonFragment(['message' => 'saved']);

    $this->assertDatabaseHas('projet_section_paragraphes', [
        'id' => $paragraphe->id,
        'titre' => 'Mon titre',
        'contenu' => '<p>Mon contenu</p>',
    ]);
});

test('un paragraphe appartenant à un autre projet retourne 404', function () {
    ['etudiant' => $etudiant, 'classe' => $classe, 'groupe' => $groupe, 'typeProjet' => $typeProjet, 'section' => $section] = creerScenarioParagraphe();

    // Autre projet dans le même groupe mais type différent
    $autreType = TypeProjet::create([
        'enseignant_id' => $classe->enseignant_id,
        'nom' => 'Autre', 'accessible' => true,
    ]);
    $autreSection = TypeProjetSection::create([
        'type_projet_id' => $autreType->id,
        'label' => 'Dev',
        'ordre' => 1,
        'type' => 'paragraphes',
    ]);
    $autreProjet = ProjetRecherche::create([
        'groupe_id' => $groupe->id,
        'type_projet_id' => $autreType->id,
    ]);
    $paragrapheAutreProjet = ProjetSectionParagraphe::create([
        'projet_id' => $autreProjet->id,
        'section_id' => $autreSection->id,
        'ordre' => 1,
    ]);

    $this->actingAs($etudiant)
        ->patchJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/sections/{$section->id}/paragraphes/{$paragrapheAutreProjet->id}", [
            'titre' => 'Injection',
        ])
        ->assertNotFound();
});

test('projet verrouillé interdit la modification d\'un paragraphe (403)', function () {
    ['etudiant' => $etudiant, 'classe' => $classe, 'groupe' => $groupe, 'typeProjet' => $typeProjet, 'projet' => $projet, 'section' => $section] = creerScenarioParagraphe();

    $paragraphe = ProjetSectionParagraphe::create([
        'projet_id' => $projet->id,
        'section_id' => $section->id,
        'ordre' => 1,
    ]);

    $projet->update(['verrouille' => true]);

    $this->actingAs($etudiant)
        ->patchJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/sections/{$section->id}/paragraphes/{$paragraphe->id}", [
            'titre' => 'Bloqué',
        ])
        ->assertForbidden();
});

// ─── destroySectionParagraphe ─────────────────────────────────────────────────

test('un membre peut supprimer un paragraphe s\'il en reste au moins 2', function () {
    ['etudiant' => $etudiant, 'classe' => $classe, 'groupe' => $groupe, 'typeProjet' => $typeProjet, 'projet' => $projet, 'section' => $section] = creerScenarioParagraphe();

    $p1 = ProjetSectionParagraphe::create(['projet_id' => $projet->id, 'section_id' => $section->id, 'ordre' => 1]);
    $p2 = ProjetSectionParagraphe::create(['projet_id' => $projet->id, 'section_id' => $section->id, 'ordre' => 2]);

    $this->actingAs($etudiant)
        ->deleteJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/sections/{$section->id}/paragraphes/{$p1->id}")
        ->assertOk()
        ->assertJsonFragment(['message' => 'deleted']);

    $this->assertDatabaseMissing('projet_section_paragraphes', ['id' => $p1->id]);
    $this->assertDatabaseHas('projet_section_paragraphes', ['id' => $p2->id, 'ordre' => 1]);
});

test('impossible de supprimer le dernier paragraphe d\'une section (422)', function () {
    ['etudiant' => $etudiant, 'classe' => $classe, 'groupe' => $groupe, 'typeProjet' => $typeProjet, 'projet' => $projet, 'section' => $section] = creerScenarioParagraphe();

    $paragraphe = ProjetSectionParagraphe::create([
        'projet_id' => $projet->id,
        'section_id' => $section->id,
        'ordre' => 1,
    ]);

    $this->actingAs($etudiant)
        ->deleteJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/sections/{$section->id}/paragraphes/{$paragraphe->id}")
        ->assertUnprocessable();
});

test('la suppression réordonne les paragraphes restants', function () {
    ['etudiant' => $etudiant, 'classe' => $classe, 'groupe' => $groupe, 'typeProjet' => $typeProjet, 'projet' => $projet, 'section' => $section] = creerScenarioParagraphe();

    $p1 = ProjetSectionParagraphe::create(['projet_id' => $projet->id, 'section_id' => $section->id, 'ordre' => 1]);
    $p2 = ProjetSectionParagraphe::create(['projet_id' => $projet->id, 'section_id' => $section->id, 'ordre' => 2]);
    $p3 = ProjetSectionParagraphe::create(['projet_id' => $projet->id, 'section_id' => $section->id, 'ordre' => 3]);

    $this->actingAs($etudiant)
        ->deleteJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/sections/{$section->id}/paragraphes/{$p1->id}")
        ->assertOk();

    $this->assertDatabaseHas('projet_section_paragraphes', ['id' => $p2->id, 'ordre' => 1]);
    $this->assertDatabaseHas('projet_section_paragraphes', ['id' => $p3->id, 'ordre' => 2]);
});

// ─── reorderSectionParagraphes ────────────────────────────────────────────────

test('l\'ordre des paragraphes est mis à jour correctement', function () {
    ['etudiant' => $etudiant, 'classe' => $classe, 'groupe' => $groupe, 'typeProjet' => $typeProjet, 'projet' => $projet, 'section' => $section] = creerScenarioParagraphe();

    $p1 = ProjetSectionParagraphe::create(['projet_id' => $projet->id, 'section_id' => $section->id, 'ordre' => 1]);
    $p2 = ProjetSectionParagraphe::create(['projet_id' => $projet->id, 'section_id' => $section->id, 'ordre' => 2]);

    $this->actingAs($etudiant)
        ->patchJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/sections/{$section->id}/paragraphes/reorder", [
            'ordre' => [$p2->id, $p1->id],
        ])
        ->assertOk()
        ->assertJsonFragment(['message' => 'reordered']);

    $this->assertDatabaseHas('projet_section_paragraphes', ['id' => $p2->id, 'ordre' => 1]);
    $this->assertDatabaseHas('projet_section_paragraphes', ['id' => $p1->id, 'ordre' => 2]);
});

test('un id inexistant dans reorder retourne 422', function () {
    ['etudiant' => $etudiant, 'classe' => $classe, 'groupe' => $groupe, 'typeProjet' => $typeProjet, 'section' => $section] = creerScenarioParagraphe();

    $this->actingAs($etudiant)
        ->patchJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/sections/{$section->id}/paragraphes/reorder", [
            'ordre' => [99999],
        ])
        ->assertUnprocessable();
});

// ─── show() retourne les sections avec paragraphes ────────────────────────────

test('le show retourne les sections de type paragraphes avec leurs paragraphes', function () {
    ['etudiant' => $etudiant, 'classe' => $classe, 'groupe' => $groupe, 'typeProjet' => $typeProjet, 'projet' => $projet, 'section' => $section] = creerScenarioParagraphe();

    ProjetSectionParagraphe::create([
        'projet_id' => $projet->id,
        'section_id' => $section->id,
        'ordre' => 1,
        'titre' => 'Premier paragraphe',
        'contenu' => '<p>Contenu</p>',
    ]);

    $this->actingAs($etudiant)
        ->get("/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/edit")
        ->assertInertia(fn ($page) => $page
            ->component('Projets/Show')
            ->has('sections', 1)
            ->where('sections.0.type', 'paragraphes')
            ->has('sections.0.paragraphes', 1)
            ->where('sections.0.paragraphes.0.ordre', 1)
            ->where('sections.0.paragraphes.0.titre', 'Premier paragraphe')
        );
});

// ─── updateConclusion avec section_id ────────────────────────────────────────

test('un membre peut sauvegarder une conclusion liée à une section', function () {
    ['etudiant' => $etudiant, 'classe' => $classe, 'groupe' => $groupe, 'typeProjet' => $typeProjet, 'projet' => $projet, 'section' => $section] = creerScenarioParagraphe();

    $this->actingAs($etudiant)
        ->putJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/conclusion", [
            'user_id' => $etudiant->id,
            'section_id' => $section->id,
            'contenu' => '<p>Ma conclusion</p>',
        ])
        ->assertOk()
        ->assertJsonFragment(['message' => 'saved']);

    $this->assertDatabaseHas('projet_conclusions', [
        'projet_id' => $projet->id,
        'user_id' => $etudiant->id,
        'section_id' => $section->id,
        'contenu' => '<p>Ma conclusion</p>',
    ]);
});

test('deux conclusions pour le même user mais sections différentes sont distinctes', function () {
    ['etudiant' => $etudiant, 'classe' => $classe, 'groupe' => $groupe, 'typeProjet' => $typeProjet, 'projet' => $projet] = creerScenarioParagraphe();

    $section2 = TypeProjetSection::create([
        'type_projet_id' => $typeProjet->id,
        'label' => 'Conclusion',
        'ordre' => 2,
        'type' => 'individuel',
    ]);

    $this->actingAs($etudiant)
        ->putJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/conclusion", [
            'user_id' => $etudiant->id,
            'contenu' => '<p>Sans section</p>',
        ])
        ->assertOk();

    $this->actingAs($etudiant)
        ->putJson("/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/conclusion", [
            'user_id' => $etudiant->id,
            'section_id' => $section2->id,
            'contenu' => '<p>Avec section</p>',
        ])
        ->assertOk();

    // Les deux entrées sont distinctes (clés différentes)
    $this->assertDatabaseCount('projet_conclusions', 2);
});

test('le show retourne les conclusionsParMembre dans les sections de type individuel', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $etudiant = User::factory()->create(['role' => 'etudiant']);

    $classe = Classe::create([
        'nom_cours' => 'Hist', 'description' => 'T', 'code' => '330-P2',
        'groupe' => '01', 'enseignant_id' => $enseignant->id,
    ]);
    $classe->etudiants()->attach($etudiant->id);

    $groupe = Groupe::create(['classe_id' => $classe->id, 'created_by' => $etudiant->id]);
    $groupe->membres()->attach($etudiant->id);

    $typeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id,
        'nom' => 'Type individuel',
        'accessible' => true,
    ]);

    $section = TypeProjetSection::create([
        'type_projet_id' => $typeProjet->id,
        'label' => 'Conclusion',
        'ordre' => 1,
        'type' => 'individuel',
    ]);

    $projet = ProjetRecherche::create([
        'groupe_id' => $groupe->id,
        'type_projet_id' => $typeProjet->id,
    ]);

    ProjetConclusion::create([
        'projet_id' => $projet->id,
        'user_id' => $etudiant->id,
        'section_id' => $section->id,
        'contenu' => '<p>Ma conclusion individuelle</p>',
    ]);

    $this->actingAs($etudiant)
        ->get("/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/edit")
        ->assertInertia(fn ($page) => $page
            ->component('Projets/Show')
            ->has('sections', 1)
            ->where('sections.0.type', 'individuel')
            ->has('sections.0.conclusionsParMembre', 1)
            ->where('sections.0.conclusionsParMembre.0.userId', $etudiant->id)
            ->where('sections.0.conclusionsParMembre.0.contenu', '<p>Ma conclusion individuelle</p>')
        );
});
