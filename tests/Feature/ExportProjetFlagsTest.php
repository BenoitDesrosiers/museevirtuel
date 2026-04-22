<?php

use App\Helpers\HtmlHelper;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use App\Models\User;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Crée le scénario minimal pour tester l'export : enseignant, cours, classe, groupe,
 * typeProjet configuré avec les flags passés et un ProjetRecherche associé.
 *
 * @param  array<string, mixed>  $flags
 * @return array{enseignant: User, cours: Cours, cs: Classe, groupe: Groupe, typeProjet: TypeProjet, projet: ProjetRecherche, etudiant: User}
 */
function creerScenarioExport(array $flags = []): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    $typeProjet = TypeProjet::create(array_merge([
        'enseignant_id' => $enseignant->id,
        'nom' => 'Projet export test',
        'accessible' => true,
        'generer_page_titre' => true,
        'generer_table_matieres' => true,
    ], $flags));

    $cours = Cours::create([
        'nom_cours' => 'Histoire du Québec',
        'description' => 'Test',
        'heures_par_semaine' => 3,
        'code' => '330-TEST',
        'groupe' => '0001',
        'enseignant_id' => $enseignant->id,
    ]);

    $etudiant = User::factory()->create(['role' => 'etudiant']);

    $cs = Classe::create(['cours_id' => $cours->id]);
    $cs->etudiants()->attach($etudiant->id);

    $groupe = Groupe::create(['classe_id' => $cs->id, 'created_by' => $etudiant->id]);
    $groupe->membres()->attach($etudiant->id);

    $projet = ProjetRecherche::create([
        'groupe_id' => $groupe->id,
        'type_projet_id' => $typeProjet->id,
    ]);

    return compact('enseignant', 'cours', 'cs', 'groupe', 'typeProjet', 'projet', 'etudiant');
}

// ─── Flags de génération — sauvegarde ─────────────────────────────────────────

test('mettre generer_page_titre à false via PUT persiste bien false en base', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $typeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id,
        'nom' => 'Projet',
        'accessible' => false,
        'generer_page_titre' => true,
        'generer_table_matieres' => true,
    ]);

    $this->actingAs($enseignant)
        ->putJson("/types-projets/{$typeProjet->id}", [
            'nom' => 'Projet',
            'generer_page_titre' => false,
            'generer_table_matieres' => true,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('types_projets', [
        'id' => $typeProjet->id,
        'generer_page_titre' => false,
        'generer_table_matieres' => true,
    ]);
});

test('mettre generer_table_matieres à false via PUT persiste bien false en base', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $typeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id,
        'nom' => 'Projet',
        'accessible' => false,
        'generer_page_titre' => true,
        'generer_table_matieres' => true,
    ]);

    $this->actingAs($enseignant)
        ->putJson("/types-projets/{$typeProjet->id}", [
            'nom' => 'Projet',
            'generer_page_titre' => true,
            'generer_table_matieres' => false,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('types_projets', [
        'id' => $typeProjet->id,
        'generer_page_titre' => true,
        'generer_table_matieres' => false,
    ]);
});

test('désactiver les deux flags simultanément persiste en base', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $typeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id,
        'nom' => 'Projet',
        'accessible' => false,
        'generer_page_titre' => true,
        'generer_table_matieres' => true,
    ]);

    $this->actingAs($enseignant)
        ->putJson("/types-projets/{$typeProjet->id}", [
            'nom' => 'Projet',
            'generer_page_titre' => false,
            'generer_table_matieres' => false,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('types_projets', [
        'id' => $typeProjet->id,
        'generer_page_titre' => false,
        'generer_table_matieres' => false,
    ]);
});

// ─── Flags de génération — rendu HTML Blade ───────────────────────────────────

test("la vue export blade n'inclut pas la page titre quand generer_page_titre est false et contenu vide", function () {
    ['groupe' => $groupe, 'cours' => $cours, 'typeProjet' => $typeProjet, 'projet' => $projet, 'enseignant' => $enseignant] =
        creerScenarioExport(['generer_page_titre' => false]);

    $projet->load(['typeProjet.sections', 'sectionContenus', 'conclusions', 'developpements', 'renvois']);
    $groupe->load(['membres', 'classe.cours.enseignant']);

    $membres = $groupe->membres->map(fn ($m) => "{$m->prenom} {$m->nom}")->values();

    $html = view('projets.export', [
        'projet' => $projet,
        'groupe' => $groupe,
        'classe' => $cours,
        'enseignant' => $enseignant,
        'membres' => $membres,
        'sections' => collect([]),
        'renvois' => collect([]),
        'genererPageTitre' => false,
        'genererTableMatieres' => true,
        'pageTitreContenu' => null,
        'tableMatieresContenu' => null,
        'stripMarks' => fn (?string $h): string => HtmlHelper::stripAnnotationMarks($h),
    ])->render();

    expect($html)->not->toContain('class="page-titre"');
});

test('la vue export blade inclut la page titre quand generer_page_titre est true', function () {
    ['groupe' => $groupe, 'cours' => $cours, 'projet' => $projet, 'enseignant' => $enseignant] =
        creerScenarioExport(['generer_page_titre' => true]);

    $projet->load(['typeProjet.sections', 'sectionContenus', 'conclusions', 'developpements', 'renvois']);
    $groupe->load(['membres', 'classe.cours.enseignant']);

    $membres = $groupe->membres->map(fn ($m) => "{$m->prenom} {$m->nom}")->values();

    $html = view('projets.export', [
        'projet' => $projet,
        'groupe' => $groupe,
        'classe' => $cours,
        'enseignant' => $enseignant,
        'membres' => $membres,
        'sections' => collect([]),
        'renvois' => collect([]),
        'genererPageTitre' => true,
        'genererTableMatieres' => false,
        'pageTitreContenu' => null,
        'tableMatieresContenu' => null,
        'stripMarks' => fn (?string $h): string => HtmlHelper::stripAnnotationMarks($h),
    ])->render();

    expect($html)->toContain('class="page-titre"');
});

test("la vue export blade n'inclut pas la table des matières quand generer_table_matieres est false et contenu vide", function () {
    ['groupe' => $groupe, 'cours' => $cours, 'projet' => $projet, 'enseignant' => $enseignant] =
        creerScenarioExport(['generer_table_matieres' => false]);

    $projet->load(['typeProjet.sections', 'sectionContenus', 'conclusions', 'developpements', 'renvois']);
    $groupe->load(['membres', 'classe.cours.enseignant']);

    $membres = $groupe->membres->map(fn ($m) => "{$m->prenom} {$m->nom}")->values();

    $html = view('projets.export', [
        'projet' => $projet,
        'groupe' => $groupe,
        'classe' => $cours,
        'enseignant' => $enseignant,
        'membres' => $membres,
        'sections' => collect([]),
        'renvois' => collect([]),
        'genererPageTitre' => false,
        'genererTableMatieres' => false,
        'pageTitreContenu' => null,
        'tableMatieresContenu' => null,
        'stripMarks' => fn (?string $h): string => HtmlHelper::stripAnnotationMarks($h),
    ])->render();

    expect($html)->not->toContain('class="toc"');
});

test('la vue blade inclut le contenu manuel de la page titre quand generer_page_titre est false', function () {
    ['groupe' => $groupe, 'cours' => $cours, 'projet' => $projet, 'enseignant' => $enseignant] =
        creerScenarioExport(['generer_page_titre' => false]);

    $projet->load(['typeProjet.sections', 'sectionContenus', 'conclusions', 'developpements', 'renvois']);
    $groupe->load(['membres', 'classe.cours.enseignant']);

    $membres = $groupe->membres->map(fn ($m) => "{$m->prenom} {$m->nom}")->values();
    $contenuManuel = '<p>Ma page titre manuelle</p>';

    $html = view('projets.export', [
        'projet' => $projet,
        'groupe' => $groupe,
        'classe' => $cours,
        'enseignant' => $enseignant,
        'membres' => $membres,
        'sections' => collect([]),
        'renvois' => collect([]),
        'genererPageTitre' => false,
        'genererTableMatieres' => true,
        'pageTitreContenu' => $contenuManuel,
        'tableMatieresContenu' => null,
        'stripMarks' => fn (?string $h): string => HtmlHelper::stripAnnotationMarks($h),
    ])->render();

    expect($html)
        ->toContain('class="page-titre"')
        ->toContain('Ma page titre manuelle');
});

test('la vue blade inclut le contenu manuel de la table des matières quand generer_table_matieres est false', function () {
    ['groupe' => $groupe, 'cours' => $cours, 'projet' => $projet, 'enseignant' => $enseignant] =
        creerScenarioExport(['generer_table_matieres' => false]);

    $projet->load(['typeProjet.sections', 'sectionContenus', 'conclusions', 'developpements', 'renvois']);
    $groupe->load(['membres', 'classe.cours.enseignant']);

    $membres = $groupe->membres->map(fn ($m) => "{$m->prenom} {$m->nom}")->values();
    $contenuManuel = '<p>Ma table des matières</p>';

    $html = view('projets.export', [
        'projet' => $projet,
        'groupe' => $groupe,
        'classe' => $cours,
        'enseignant' => $enseignant,
        'membres' => $membres,
        'sections' => collect([]),
        'renvois' => collect([]),
        'genererPageTitre' => false,
        'genererTableMatieres' => false,
        'pageTitreContenu' => null,
        'tableMatieresContenu' => $contenuManuel,
        'stripMarks' => fn (?string $h): string => HtmlHelper::stripAnnotationMarks($h),
    ])->render();

    expect($html)
        ->toContain('class="toc"')
        ->toContain('Ma table des matières');
});

// ─── Sauvegarde du contenu front-matter manuel via PUT ────────────────────────

test('PUT projet persiste page_titre_contenu en base', function () {
    ['cours' => $cours, 'cs' => $cs, 'groupe' => $groupe, 'typeProjet' => $typeProjet, 'etudiant' => $etudiant, 'projet' => $projet] =
        creerScenarioExport(['generer_page_titre' => false]);

    $this->actingAs($etudiant)
        ->putJson("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}", [
            'page_titre_contenu' => '<p>Ma page titre</p>',
        ])
        ->assertOk();

    $this->assertDatabaseHas('projets_recherche', [
        'id' => $projet->id,
        'page_titre_contenu' => '<p>Ma page titre</p>',
    ]);
});

test('PUT projet persiste table_matieres_contenu en base', function () {
    ['cours' => $cours, 'cs' => $cs, 'groupe' => $groupe, 'typeProjet' => $typeProjet, 'etudiant' => $etudiant, 'projet' => $projet] =
        creerScenarioExport(['generer_table_matieres' => false]);

    $this->actingAs($etudiant)
        ->putJson("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}", [
            'table_matieres_contenu' => '<p>Ma table des matières</p>',
        ])
        ->assertOk();

    $this->assertDatabaseHas('projets_recherche', [
        'id' => $projet->id,
        'table_matieres_contenu' => '<p>Ma table des matières</p>',
    ]);
});

test("l'export PDF retourne 200 avec contenu page titre manuel", function () {
    ['cours' => $cours, 'cs' => $cs, 'groupe' => $groupe, 'typeProjet' => $typeProjet, 'etudiant' => $etudiant, 'projet' => $projet] =
        creerScenarioExport(['generer_page_titre' => false, 'generer_table_matieres' => false]);

    $projet->update(['page_titre_contenu' => '<p>Page titre manuelle</p>']);

    $this->actingAs($etudiant)
        ->get("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/pdf")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

test("l'export Word retourne 200 avec contenu table des matières manuel", function () {
    ['cours' => $cours, 'cs' => $cs, 'groupe' => $groupe, 'typeProjet' => $typeProjet, 'etudiant' => $etudiant, 'projet' => $projet] =
        creerScenarioExport(['generer_page_titre' => false, 'generer_table_matieres' => false]);

    $projet->update(['table_matieres_contenu' => '<p>Table des matières manuelle</p>']);

    $this->actingAs($etudiant)
        ->get("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/word")
        ->assertOk()
        ->assertHeader(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        );
});

// ─── Flags de génération — export PDF et Word complets ────────────────────────

test("l'export PDF retourne 200 quand les deux flags sont désactivés", function () {
    ['cours' => $cours, 'cs' => $cs, 'groupe' => $groupe, 'typeProjet' => $typeProjet, 'etudiant' => $etudiant] =
        creerScenarioExport(['generer_page_titre' => false, 'generer_table_matieres' => false]);

    $this->actingAs($etudiant)
        ->get("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/pdf")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

test("l'export Word retourne 200 quand les deux flags sont désactivés", function () {
    ['cours' => $cours, 'cs' => $cs, 'groupe' => $groupe, 'typeProjet' => $typeProjet, 'etudiant' => $etudiant] =
        creerScenarioExport(['generer_page_titre' => false, 'generer_table_matieres' => false]);

    $this->actingAs($etudiant)
        ->get("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/word")
        ->assertOk()
        ->assertHeader(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        );
});
