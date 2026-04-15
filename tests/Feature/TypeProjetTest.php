<?php

use App\Models\Classe;
use App\Models\Cours;
use App\Models\GrilleCorrection;
use App\Models\ProjetRecherche;
use App\Models\ProjetSectionContenu;
use App\Models\TypeProjet;
use App\Models\TypeProjetSection;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

// ─── Index ────────────────────────────────────────────────────────────────────

test("l'enseignant voit la liste de ses types de projet", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    TypeProjet::create(['enseignant_id' => $enseignant->id, 'nom' => 'Projet A', 'accessible' => false]);
    TypeProjet::create(['enseignant_id' => $enseignant->id, 'nom' => 'Projet B', 'accessible' => true]);

    $this->actingAs($enseignant)
        ->get('/types-projets')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('TypeProjet/Index')
            ->has('typesProjets', 2)
        );
});

test("l'enseignant ne voit pas les types de projet des autres enseignants", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $autre = User::factory()->create(['role' => 'enseignant']);

    TypeProjet::create(['enseignant_id' => $autre->id, 'nom' => 'Type de A', 'accessible' => false]);

    $this->actingAs($enseignant)
        ->get('/types-projets')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('typesProjets', 0)
        );
});

test('un étudiant est redirigé depuis la liste des types de projet', function () {
    $etudiant = User::factory()->create(['role' => 'etudiant']);

    $this->actingAs($etudiant)
        ->get('/types-projets')
        ->assertRedirect();
});

// ─── Store ────────────────────────────────────────────────────────────────────

test("l'enseignant peut créer un type de projet", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    $this->actingAs($enseignant)
        ->post('/types-projets', [
            'nom' => 'Projet de recherche',
            'description' => 'Un projet sympa',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('types_projets', [
        'enseignant_id' => $enseignant->id,
        'nom' => 'Projet de recherche',
        'accessible' => false,
    ]);
});

test('le nom est obligatoire lors de la création', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    $this->actingAs($enseignant)
        ->postJson('/types-projets', ['nom' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['nom']);
});

// ─── Store avec paramètres de remise ─────────────────────────────────────────

test("l'enseignant peut créer un type de projet avec des paramètres de remise", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    $this->actingAs($enseignant)
        ->post('/types-projets', [
            'nom' => 'Projet avec remise',
            'date_remise' => '2026-05-01T23:59',
            'remises_multiples' => true,
            'retard_permis' => false,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('types_projets', [
        'enseignant_id' => $enseignant->id,
        'nom' => 'Projet avec remise',
        'remises_multiples' => true,
        'retard_permis' => false,
    ]);
});

// ─── Update ───────────────────────────────────────────────────────────────────

test("l'enseignant peut modifier son type de projet", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $typeProjet = TypeProjet::create(['enseignant_id' => $enseignant->id, 'nom' => 'Ancien nom', 'accessible' => false]);

    $this->actingAs($enseignant)
        ->put("/types-projets/{$typeProjet->id}", [
            'nom' => 'Nouveau nom',
            'description' => 'Nouvelle description',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('types_projets', [
        'id' => $typeProjet->id,
        'nom' => 'Nouveau nom',
    ]);
});

test("l'enseignant peut configurer les paramètres de remise via update", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $typeProjet = TypeProjet::create(['enseignant_id' => $enseignant->id, 'nom' => 'Projet', 'accessible' => false]);

    $this->actingAs($enseignant)
        ->put("/types-projets/{$typeProjet->id}", [
            'nom' => 'Projet',
            'date_remise' => '2026-06-15T23:59',
            'remises_multiples' => true,
            'retard_permis' => true,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('types_projets', [
        'id' => $typeProjet->id,
        'remises_multiples' => true,
        'retard_permis' => true,
    ]);
});

test("un enseignant ne peut pas modifier le type de projet d'un autre enseignant (IDOR)", function () {
    $enseignantA = User::factory()->create(['role' => 'enseignant']);
    $enseignantB = User::factory()->create(['role' => 'enseignant']);

    $typeProjet = TypeProjet::create(['enseignant_id' => $enseignantA->id, 'nom' => 'Type de A', 'accessible' => false]);

    $this->actingAs($enseignantB)
        ->put("/types-projets/{$typeProjet->id}", ['nom' => 'Piraté'])
        ->assertForbidden();

    $this->assertDatabaseHas('types_projets', ['id' => $typeProjet->id, 'nom' => 'Type de A']);
});

// ─── Toggle accessible ───────────────────────────────────────────────────────

test("l'enseignant peut basculer l'accessibilité de son type de projet", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $typeProjet = TypeProjet::create(['enseignant_id' => $enseignant->id, 'nom' => 'Type X', 'accessible' => false]);

    $this->actingAs($enseignant)
        ->patch("/types-projets/{$typeProjet->id}/toggle-accessible")
        ->assertRedirect();

    $this->assertDatabaseHas('types_projets', ['id' => $typeProjet->id, 'accessible' => true]);

    $this->actingAs($enseignant)
        ->patch("/types-projets/{$typeProjet->id}/toggle-accessible");

    $this->assertDatabaseHas('types_projets', ['id' => $typeProjet->id, 'accessible' => false]);
});

test("un enseignant ne peut pas toggler l'accessibilité du type de projet d'un autre (IDOR)", function () {
    $enseignantA = User::factory()->create(['role' => 'enseignant']);
    $enseignantB = User::factory()->create(['role' => 'enseignant']);

    $typeProjet = TypeProjet::create(['enseignant_id' => $enseignantA->id, 'nom' => 'Type de A', 'accessible' => false]);

    $this->actingAs($enseignantB)
        ->patch("/types-projets/{$typeProjet->id}/toggle-accessible")
        ->assertForbidden();
});

// ─── Destroy ──────────────────────────────────────────────────────────────────

test("l'enseignant peut supprimer son type de projet", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $typeProjet = TypeProjet::create(['enseignant_id' => $enseignant->id, 'nom' => 'À supprimer', 'accessible' => false]);

    $this->actingAs($enseignant)
        ->delete("/types-projets/{$typeProjet->id}")
        ->assertRedirect();

    $this->assertDatabaseMissing('types_projets', ['id' => $typeProjet->id]);
});

test('la suppression du type de projet cascade sur la grille de correction', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $typeProjet = TypeProjet::create(['enseignant_id' => $enseignant->id, 'nom' => 'Avec grille', 'accessible' => false]);
    $grille = GrilleCorrection::create(['type_projet_id' => $typeProjet->id, 'nom' => 'Grille à supprimer']);

    $this->actingAs($enseignant)
        ->delete("/types-projets/{$typeProjet->id}")
        ->assertRedirect();

    $this->assertDatabaseMissing('grilles_correction', ['id' => $grille->id]);
});

test("un enseignant ne peut pas supprimer le type de projet d'un autre enseignant (IDOR)", function () {
    $enseignantA = User::factory()->create(['role' => 'enseignant']);
    $enseignantB = User::factory()->create(['role' => 'enseignant']);

    $typeProjet = TypeProjet::create(['enseignant_id' => $enseignantA->id, 'nom' => 'Type de A', 'accessible' => false]);

    $this->actingAs($enseignantB)
        ->delete("/types-projets/{$typeProjet->id}")
        ->assertForbidden();

    $this->assertDatabaseHas('types_projets', ['id' => $typeProjet->id]);
});

// ─── Accessibilité étudiants ─────────────────────────────────────────────────

test("un étudiant ne peut pas accéder au show d'un projet si le type n'est pas accessible", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $etudiant = User::factory()->create(['role' => 'etudiant']);

    $cours = Cours::create([
        'nom_cours' => 'Hist',
        'description' => 'T',
        'code' => '330-T1',
        'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);
    $cours->etudiants()->attach($etudiant->id);

    $classe = Classe::create(['cours_id' => $cours->id, 'created_by' => $etudiant->id]);
    $classe->membres()->attach($etudiant->id);

    $typeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id,
        'nom' => 'Type non accessible',
        'accessible' => false, // non accessible
    ]);

    ProjetRecherche::create([
        'classe_id' => $classe->id,
        'type_projet_id' => $typeProjet->id,
    ]);

    $this->actingAs($etudiant)
        ->get("/cours/{$cours->id}/classes/{$classe->id}/projets/{$typeProjet->id}/edit")
        ->assertForbidden();
});

// ─── Sections CRUD ────────────────────────────────────────────────────────────

test("l'enseignant peut ajouter une section à son type de projet", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $typeProjet = TypeProjet::create(['enseignant_id' => $enseignant->id, 'nom' => 'Projet', 'accessible' => false]);

    $this->actingAs($enseignant)
        ->post("/types-projets/{$typeProjet->id}/sections", [
            'label' => 'Introduction',
            'description' => 'Présentez le sujet',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('type_projet_sections', [
        'type_projet_id' => $typeProjet->id,
        'label' => 'Introduction',
        'description' => 'Présentez le sujet',
        'ordre' => 1,
    ]);
});

test("l'ordre des sections est incrémenté automatiquement", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $typeProjet = TypeProjet::create(['enseignant_id' => $enseignant->id, 'nom' => 'Projet', 'accessible' => false]);

    $this->actingAs($enseignant)
        ->post("/types-projets/{$typeProjet->id}/sections", ['label' => 'Section A']);
    $this->actingAs($enseignant)
        ->post("/types-projets/{$typeProjet->id}/sections", ['label' => 'Section B']);

    $this->assertDatabaseHas('type_projet_sections', ['label' => 'Section A', 'ordre' => 1]);
    $this->assertDatabaseHas('type_projet_sections', ['label' => 'Section B', 'ordre' => 2]);
});

test("l'enseignant peut modifier une section de son type de projet", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $typeProjet = TypeProjet::create(['enseignant_id' => $enseignant->id, 'nom' => 'Projet', 'accessible' => false]);
    $section = TypeProjetSection::create(['type_projet_id' => $typeProjet->id, 'label' => 'Ancien label', 'ordre' => 1]);

    $this->actingAs($enseignant)
        ->put("/types-projets/{$typeProjet->id}/sections/{$section->id}", [
            'label' => 'Nouveau label',
            'description' => 'Nouvelle consigne',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('type_projet_sections', [
        'id' => $section->id,
        'label' => 'Nouveau label',
        'description' => 'Nouvelle consigne',
    ]);
});

test("un enseignant ne peut pas modifier la section d'un autre enseignant (IDOR)", function () {
    $enseignantA = User::factory()->create(['role' => 'enseignant']);
    $enseignantB = User::factory()->create(['role' => 'enseignant']);

    $typeProjet = TypeProjet::create(['enseignant_id' => $enseignantA->id, 'nom' => 'Projet A', 'accessible' => false]);
    $section = TypeProjetSection::create(['type_projet_id' => $typeProjet->id, 'label' => 'Section de A', 'ordre' => 1]);

    $this->actingAs($enseignantB)
        ->put("/types-projets/{$typeProjet->id}/sections/{$section->id}", ['label' => 'Piraté'])
        ->assertForbidden();

    $this->assertDatabaseHas('type_projet_sections', ['id' => $section->id, 'label' => 'Section de A']);
});

test("l'enseignant peut supprimer une section et les ordres sont renumérotés", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $typeProjet = TypeProjet::create(['enseignant_id' => $enseignant->id, 'nom' => 'Projet', 'accessible' => false]);
    $s1 = TypeProjetSection::create(['type_projet_id' => $typeProjet->id, 'label' => 'S1', 'ordre' => 1]);
    $s2 = TypeProjetSection::create(['type_projet_id' => $typeProjet->id, 'label' => 'S2', 'ordre' => 2]);
    $s3 = TypeProjetSection::create(['type_projet_id' => $typeProjet->id, 'label' => 'S3', 'ordre' => 3]);

    $this->actingAs($enseignant)
        ->delete("/types-projets/{$typeProjet->id}/sections/{$s2->id}")
        ->assertRedirect();

    $this->assertDatabaseMissing('type_projet_sections', ['id' => $s2->id]);
    $this->assertDatabaseHas('type_projet_sections', ['id' => $s1->id, 'ordre' => 1]);
    $this->assertDatabaseHas('type_projet_sections', ['id' => $s3->id, 'ordre' => 2]);
});

test("la suppression d'une section cascade sur les contenus des projets", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $etudiant = User::factory()->create(['role' => 'etudiant']);

    $cours = Cours::create([
        'nom_cours' => 'Hist', 'description' => 'T', 'code' => '330-T1',
        'groupe' => '01', 'enseignant_id' => $enseignant->id,
    ]);
    $cours->etudiants()->attach($etudiant->id);
    $classe = Classe::create(['cours_id' => $cours->id, 'created_by' => $etudiant->id]);

    $typeProjet = TypeProjet::create(['enseignant_id' => $enseignant->id, 'nom' => 'Projet', 'accessible' => false]);
    $section = TypeProjetSection::create(['type_projet_id' => $typeProjet->id, 'label' => 'Intro', 'ordre' => 1]);

    $projet = ProjetRecherche::create(['classe_id' => $classe->id, 'type_projet_id' => $typeProjet->id]);
    ProjetSectionContenu::create(['projet_id' => $projet->id, 'section_id' => $section->id, 'contenu' => '<p>Texte</p>']);

    $this->actingAs($enseignant)
        ->delete("/types-projets/{$typeProjet->id}/sections/{$section->id}")
        ->assertRedirect();

    $this->assertDatabaseMissing('projet_section_contenus', ['section_id' => $section->id]);
});

// ─── Store avec sections ───────────────────────────────────────────────────────

test("l'enseignant peut créer un type de projet avec des sections inline", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    $this->actingAs($enseignant)
        ->post('/types-projets', [
            'nom' => 'Projet avec sections',
            'sections' => [
                ['label' => 'Introduction', 'description' => 'Présentez le sujet'],
                ['label' => 'Développement'],
            ],
        ])
        ->assertRedirect();

    $typeProjet = TypeProjet::where('nom', 'Projet avec sections')->first();

    $this->assertDatabaseHas('type_projet_sections', [
        'type_projet_id' => $typeProjet->id,
        'label' => 'Introduction',
        'description' => 'Présentez le sujet',
        'ordre' => 1,
    ]);
    $this->assertDatabaseHas('type_projet_sections', [
        'type_projet_id' => $typeProjet->id,
        'label' => 'Développement',
        'ordre' => 2,
    ]);
});

// ─── Update avec sections ─────────────────────────────────────────────────────

test("l'enseignant peut ajouter des sections via update", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $typeProjet = TypeProjet::create(['enseignant_id' => $enseignant->id, 'nom' => 'Projet', 'accessible' => false]);

    $this->actingAs($enseignant)
        ->put("/types-projets/{$typeProjet->id}", [
            'nom' => 'Projet',
            'sections' => [
                ['label' => 'Section A'],
                ['label' => 'Section B'],
            ],
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('type_projet_sections', ['type_projet_id' => $typeProjet->id, 'label' => 'Section A', 'ordre' => 1]);
    $this->assertDatabaseHas('type_projet_sections', ['type_projet_id' => $typeProjet->id, 'label' => 'Section B', 'ordre' => 2]);
});

test("l'update supprime les sections retirées et leur contenu cascade", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $etudiant = User::factory()->create(['role' => 'etudiant']);

    $cours = Cours::create(['nom_cours' => 'Hist', 'description' => 'T', 'code' => '330-T1', 'groupe' => '01', 'enseignant_id' => $enseignant->id]);
    $cours->etudiants()->attach($etudiant->id);
    $classe = Classe::create(['cours_id' => $cours->id, 'created_by' => $etudiant->id]);

    $typeProjet = TypeProjet::create(['enseignant_id' => $enseignant->id, 'nom' => 'Projet', 'accessible' => false]);
    $s1 = TypeProjetSection::create(['type_projet_id' => $typeProjet->id, 'label' => 'S1', 'ordre' => 1]);
    $s2 = TypeProjetSection::create(['type_projet_id' => $typeProjet->id, 'label' => 'S2', 'ordre' => 2]);

    $projet = ProjetRecherche::create(['classe_id' => $classe->id, 'type_projet_id' => $typeProjet->id]);
    ProjetSectionContenu::create(['projet_id' => $projet->id, 'section_id' => $s2->id, 'contenu' => '<p>Texte</p>']);

    // Envoyer uniquement s1 — s2 doit être supprimé avec son contenu
    $this->actingAs($enseignant)
        ->put("/types-projets/{$typeProjet->id}", [
            'nom' => 'Projet',
            'sections' => [
                ['id' => $s1->id, 'label' => 'S1 modifiée'],
            ],
        ])
        ->assertRedirect();

    $this->assertDatabaseMissing('type_projet_sections', ['id' => $s2->id]);
    $this->assertDatabaseMissing('projet_section_contenus', ['section_id' => $s2->id]);
    $this->assertDatabaseHas('type_projet_sections', ['id' => $s1->id, 'label' => 'S1 modifiée', 'ordre' => 1]);
});

test("l'update sans clé sections ne modifie pas les sections existantes", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $typeProjet = TypeProjet::create(['enseignant_id' => $enseignant->id, 'nom' => 'Projet', 'accessible' => false]);
    TypeProjetSection::create(['type_projet_id' => $typeProjet->id, 'label' => 'Intro', 'ordre' => 1]);

    $this->actingAs($enseignant)
        ->put("/types-projets/{$typeProjet->id}", ['nom' => 'Nouveau nom'])
        ->assertRedirect();

    expect(TypeProjetSection::where('type_projet_id', $typeProjet->id)->count())->toBe(1);
});

// ─── Edit (page dédiée) ────────────────────────────────────────────────────────

test("l'enseignant accède à la page d'édition de son type de projet", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $typeProjet = TypeProjet::create(['enseignant_id' => $enseignant->id, 'nom' => 'Projet X', 'accessible' => false]);
    TypeProjetSection::create(['type_projet_id' => $typeProjet->id, 'label' => 'Introduction', 'ordre' => 1, 'type' => 'texte']);

    $this->actingAs($enseignant)
        ->get("/types-projets/{$typeProjet->id}/edit")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('TypeProjet/Edit')
            ->has('typeProjet', fn (AssertableInertia $tp) => $tp
                ->where('id', $typeProjet->id)
                ->where('nom', 'Projet X')
                ->has('sections', 1)
                ->etc()
            )
        );
});

test("un enseignant ne peut pas accéder à la page d'édition d'un autre enseignant", function () {
    $enseignantA = User::factory()->create(['role' => 'enseignant']);
    $enseignantB = User::factory()->create(['role' => 'enseignant']);
    $typeProjet = TypeProjet::create(['enseignant_id' => $enseignantA->id, 'nom' => 'Projet A', 'accessible' => false]);

    $this->actingAs($enseignantB)
        ->get("/types-projets/{$typeProjet->id}/edit")
        ->assertForbidden();
});

test("un étudiant est redirigé depuis la page d'édition d'un type de projet", function () {
    $etudiant = User::factory()->create(['role' => 'etudiant']);
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $typeProjet = TypeProjet::create(['enseignant_id' => $enseignant->id, 'nom' => 'Projet', 'accessible' => false]);

    $this->actingAs($etudiant)
        ->get("/types-projets/{$typeProjet->id}/edit")
        ->assertRedirect();
});

// ─────────────────────────────────────────────────────────────────────────────

test("l'enseignant peut réordonner les sections", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $typeProjet = TypeProjet::create(['enseignant_id' => $enseignant->id, 'nom' => 'Projet', 'accessible' => false]);
    $s1 = TypeProjetSection::create(['type_projet_id' => $typeProjet->id, 'label' => 'S1', 'ordre' => 1]);
    $s2 = TypeProjetSection::create(['type_projet_id' => $typeProjet->id, 'label' => 'S2', 'ordre' => 2]);
    $s3 = TypeProjetSection::create(['type_projet_id' => $typeProjet->id, 'label' => 'S3', 'ordre' => 3]);

    $this->actingAs($enseignant)
        ->put("/types-projets/{$typeProjet->id}/sections/reorder", [
            'ordre' => [$s3->id, $s1->id, $s2->id],
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('type_projet_sections', ['id' => $s3->id, 'ordre' => 1]);
    $this->assertDatabaseHas('type_projet_sections', ['id' => $s1->id, 'ordre' => 2]);
    $this->assertDatabaseHas('type_projet_sections', ['id' => $s2->id, 'ordre' => 3]);
});
