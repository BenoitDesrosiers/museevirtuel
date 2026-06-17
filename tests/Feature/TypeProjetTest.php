<?php

use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetRecherche;
use App\Models\ProjetSectionContenu;
use App\Models\TypeProjet;
use App\Models\TypeProjetSection;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Crée un cours minimal pour l'enseignant donné.
 */
function creerCours(User $enseignant): Cours
{
    return Cours::create([
        'nom_cours' => 'Cours test',
        'code' => '330-T1',
        'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);
}

// ─── Index ────────────────────────────────────────────────────────────────────

test("l'enseignant voit la liste des types de projet de son cours", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = creerCours($enseignant);

    TypeProjet::create(['enseignant_id' => $enseignant->id, 'cours_id' => $cours->id, 'nom' => 'Projet A', 'accessible' => false]);
    TypeProjet::create(['enseignant_id' => $enseignant->id, 'cours_id' => $cours->id, 'nom' => 'Projet B', 'accessible' => true]);

    $this->actingAs($enseignant)
        ->get("/cours/{$cours->id}/types-projets")
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('TypeProjet/Index')
            ->has('typesProjets', 2)
        );
});

test("les types de projet d'un autre cours ne sont pas visibles depuis un cours différent", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $autre = User::factory()->create(['role' => 'enseignant']);
    $cours = creerCours($enseignant);
    $autresCours = Cours::create([
        'nom_cours' => 'Autre cours', 'code' => '330-T2', 'groupe' => '01',
        'enseignant_id' => $autre->id,
    ]);

    TypeProjet::create(['enseignant_id' => $autre->id, 'cours_id' => $autresCours->id, 'nom' => 'Type de A', 'accessible' => false]);

    $this->actingAs($enseignant)
        ->get("/cours/{$cours->id}/types-projets")
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('typesProjets', 0)
        );
});

test("un enseignant ne peut pas voir les types de projet d'un cours qui ne lui appartient pas (IDOR)", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $autre = User::factory()->create(['role' => 'enseignant']);
    $autresCours = Cours::create([
        'nom_cours' => 'Autre cours', 'code' => '330-T2', 'groupe' => '01',
        'enseignant_id' => $autre->id,
    ]);

    $this->actingAs($enseignant)
        ->get("/cours/{$autresCours->id}/types-projets")
        ->assertForbidden();
});

test('un étudiant est redirigé depuis la liste des types de projet', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $etudiant = User::factory()->create(['role' => 'etudiant']);
    $cours = creerCours($enseignant);

    $this->actingAs($etudiant)
        ->get("/cours/{$cours->id}/types-projets")
        ->assertRedirect();
});

// ─── Create (page dédiée) ─────────────────────────────────────────────────────

test("l'enseignant accède à la page de création d'un type de projet", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = creerCours($enseignant);

    $this->actingAs($enseignant)
        ->get("/cours/{$cours->id}/types-projets/create")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('TypeProjet/Create')
        );
});

test('un étudiant est redirigé depuis la page de création de type de projet', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $etudiant = User::factory()->create(['role' => 'etudiant']);
    $cours = creerCours($enseignant);

    $this->actingAs($etudiant)
        ->get("/cours/{$cours->id}/types-projets/create")
        ->assertRedirect();
});

test('la création redirige vers la page edit du nouveau type de projet', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = creerCours($enseignant);

    $this->actingAs($enseignant)
        ->post("/cours/{$cours->id}/types-projets", ['nom' => 'Nouveau projet'])
        ->assertRedirect();

    $typeProjet = TypeProjet::where('nom', 'Nouveau projet')->first();
    expect($typeProjet)->not->toBeNull();
});

// ─── Store ────────────────────────────────────────────────────────────────────

test("l'enseignant peut créer un type de projet", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = creerCours($enseignant);

    $this->actingAs($enseignant)
        ->post("/cours/{$cours->id}/types-projets", [
            'nom' => 'Projet de recherche',
            'description' => 'Un projet sympa',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('types_projets', [
        'enseignant_id' => $enseignant->id,
        'cours_id' => $cours->id,
        'nom' => 'Projet de recherche',
        'accessible' => false,
    ]);
});

test('le nom est obligatoire lors de la création', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = creerCours($enseignant);

    $this->actingAs($enseignant)
        ->postJson("/cours/{$cours->id}/types-projets", ['nom' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['nom']);
});

// ─── Store avec paramètres de remise ─────────────────────────────────────────

test("l'enseignant peut créer un type de projet avec des paramètres de remise", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = creerCours($enseignant);

    $this->actingAs($enseignant)
        ->post("/cours/{$cours->id}/types-projets", [
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

// ─── Store — flags de génération ─────────────────────────────────────────────

test('les flags de génération sont true par défaut lors de la création', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = creerCours($enseignant);

    $this->actingAs($enseignant)
        ->post("/cours/{$cours->id}/types-projets", ['nom' => 'Projet sans flags'])
        ->assertRedirect();

    $this->assertDatabaseHas('types_projets', [
        'enseignant_id' => $enseignant->id,
        'generer_page_titre' => true,
        'generer_table_matieres' => true,
    ]);
});

test("l'enseignant peut désactiver la page titre lors de la création", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = creerCours($enseignant);

    $this->actingAs($enseignant)
        ->post("/cours/{$cours->id}/types-projets", [
            'nom' => 'Projet sans page titre',
            'generer_page_titre' => false,
            'generer_table_matieres' => true,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('types_projets', [
        'enseignant_id' => $enseignant->id,
        'generer_page_titre' => false,
        'generer_table_matieres' => true,
    ]);
});

test("l'enseignant peut désactiver la table des matières lors de la création", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = creerCours($enseignant);

    $this->actingAs($enseignant)
        ->post("/cours/{$cours->id}/types-projets", [
            'nom' => 'Projet sans TDM',
            'generer_page_titre' => true,
            'generer_table_matieres' => false,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('types_projets', [
        'enseignant_id' => $enseignant->id,
        'generer_page_titre' => true,
        'generer_table_matieres' => false,
    ]);
});

test("l'enseignant peut modifier les flags de génération via update", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = creerCours($enseignant);
    $typeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id,
        'cours_id' => $cours->id,
        'nom' => 'Projet',
        'accessible' => false,
        'generer_page_titre' => true,
        'generer_table_matieres' => true,
    ]);

    $this->actingAs($enseignant)
        ->put("/cours/{$cours->id}/types-projets/{$typeProjet->id}", [
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

// ─── Update ───────────────────────────────────────────────────────────────────

test("l'enseignant peut modifier son type de projet", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = creerCours($enseignant);
    $typeProjet = TypeProjet::create(['enseignant_id' => $enseignant->id, 'cours_id' => $cours->id, 'nom' => 'Ancien nom', 'accessible' => false]);

    $this->actingAs($enseignant)
        ->put("/cours/{$cours->id}/types-projets/{$typeProjet->id}", [
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
    $cours = creerCours($enseignant);
    $typeProjet = TypeProjet::create(['enseignant_id' => $enseignant->id, 'cours_id' => $cours->id, 'nom' => 'Projet', 'accessible' => false]);

    $this->actingAs($enseignant)
        ->put("/cours/{$cours->id}/types-projets/{$typeProjet->id}", [
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
    $cours = creerCours($enseignantA);

    $typeProjet = TypeProjet::create(['enseignant_id' => $enseignantA->id, 'cours_id' => $cours->id, 'nom' => 'Type de A', 'accessible' => false]);

    $this->actingAs($enseignantB)
        ->put("/cours/{$cours->id}/types-projets/{$typeProjet->id}", ['nom' => 'Piraté'])
        ->assertForbidden();

    $this->assertDatabaseHas('types_projets', ['id' => $typeProjet->id, 'nom' => 'Type de A']);
});

// ─── Toggle accessible ───────────────────────────────────────────────────────

test("l'enseignant peut basculer l'accessibilité de son type de projet", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = creerCours($enseignant);
    $typeProjet = TypeProjet::create(['enseignant_id' => $enseignant->id, 'cours_id' => $cours->id, 'nom' => 'Type X', 'accessible' => false]);

    $this->actingAs($enseignant)
        ->patch("/cours/{$cours->id}/types-projets/{$typeProjet->id}/toggle-accessible")
        ->assertRedirect();

    $this->assertDatabaseHas('types_projets', ['id' => $typeProjet->id, 'accessible' => true]);

    $this->actingAs($enseignant)
        ->patch("/cours/{$cours->id}/types-projets/{$typeProjet->id}/toggle-accessible");

    $this->assertDatabaseHas('types_projets', ['id' => $typeProjet->id, 'accessible' => false]);
});

test("un enseignant ne peut pas toggler l'accessibilité du type de projet d'un autre (IDOR)", function () {
    $enseignantA = User::factory()->create(['role' => 'enseignant']);
    $enseignantB = User::factory()->create(['role' => 'enseignant']);
    $cours = creerCours($enseignantA);

    $typeProjet = TypeProjet::create(['enseignant_id' => $enseignantA->id, 'cours_id' => $cours->id, 'nom' => 'Type de A', 'accessible' => false]);

    $this->actingAs($enseignantB)
        ->patch("/cours/{$cours->id}/types-projets/{$typeProjet->id}/toggle-accessible")
        ->assertForbidden();
});

// ─── Destroy ──────────────────────────────────────────────────────────────────

test("l'enseignant peut supprimer son type de projet", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = creerCours($enseignant);
    $typeProjet = TypeProjet::create(['enseignant_id' => $enseignant->id, 'cours_id' => $cours->id, 'nom' => 'À supprimer', 'accessible' => false]);

    $this->actingAs($enseignant)
        ->delete("/cours/{$cours->id}/types-projets/{$typeProjet->id}")
        ->assertRedirect();

    $this->assertDatabaseMissing('types_projets', ['id' => $typeProjet->id]);
});

test("un enseignant ne peut pas supprimer le type de projet d'un autre enseignant (IDOR)", function () {
    $enseignantA = User::factory()->create(['role' => 'enseignant']);
    $enseignantB = User::factory()->create(['role' => 'enseignant']);
    $cours = creerCours($enseignantA);

    $typeProjet = TypeProjet::create(['enseignant_id' => $enseignantA->id, 'cours_id' => $cours->id, 'nom' => 'Type de A', 'accessible' => false]);

    $this->actingAs($enseignantB)
        ->delete("/cours/{$cours->id}/types-projets/{$typeProjet->id}")
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
    $classeSection = Classe::create(['cours_id' => $cours->id]);
    $classeSection->etudiants()->attach($etudiant->id);

    $classe = Groupe::create(['classe_id' => $classeSection->id, 'created_by' => $etudiant->id]);
    $classe->membres()->attach($etudiant->id);

    $typeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id,
        'cours_id' => $cours->id,
        'nom' => 'Type non accessible',
        'accessible' => false,
    ]);

    ProjetRecherche::create([
        'groupe_id' => $classe->id,
        'type_projet_id' => $typeProjet->id,
    ]);

    $this->actingAs($etudiant)
        ->get("/cours/{$cours->id}/classes/{$classeSection->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/edit")
        ->assertForbidden();
});

// ─── Sections CRUD ────────────────────────────────────────────────────────────

test("l'enseignant peut ajouter une section à son type de projet", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = creerCours($enseignant);
    $typeProjet = TypeProjet::create(['enseignant_id' => $enseignant->id, 'cours_id' => $cours->id, 'nom' => 'Projet', 'accessible' => false]);

    $this->actingAs($enseignant)
        ->post("/cours/{$cours->id}/types-projets/{$typeProjet->id}/sections", [
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
    $cours = creerCours($enseignant);
    $typeProjet = TypeProjet::create(['enseignant_id' => $enseignant->id, 'cours_id' => $cours->id, 'nom' => 'Projet', 'accessible' => false]);

    $this->actingAs($enseignant)
        ->post("/cours/{$cours->id}/types-projets/{$typeProjet->id}/sections", ['label' => 'Section A']);
    $this->actingAs($enseignant)
        ->post("/cours/{$cours->id}/types-projets/{$typeProjet->id}/sections", ['label' => 'Section B']);

    $this->assertDatabaseHas('type_projet_sections', ['label' => 'Section A', 'ordre' => 1]);
    $this->assertDatabaseHas('type_projet_sections', ['label' => 'Section B', 'ordre' => 2]);
});

test("l'enseignant peut modifier une section de son type de projet", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = creerCours($enseignant);
    $typeProjet = TypeProjet::create(['enseignant_id' => $enseignant->id, 'cours_id' => $cours->id, 'nom' => 'Projet', 'accessible' => false]);
    $section = TypeProjetSection::create(['type_projet_id' => $typeProjet->id, 'label' => 'Ancien label', 'ordre' => 1]);

    $this->actingAs($enseignant)
        ->put("/cours/{$cours->id}/types-projets/{$typeProjet->id}/sections/{$section->id}", [
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
    $cours = creerCours($enseignantA);

    $typeProjet = TypeProjet::create(['enseignant_id' => $enseignantA->id, 'cours_id' => $cours->id, 'nom' => 'Projet A', 'accessible' => false]);
    $section = TypeProjetSection::create(['type_projet_id' => $typeProjet->id, 'label' => 'Section de A', 'ordre' => 1]);

    $this->actingAs($enseignantB)
        ->put("/cours/{$cours->id}/types-projets/{$typeProjet->id}/sections/{$section->id}", ['label' => 'Piraté'])
        ->assertForbidden();

    $this->assertDatabaseHas('type_projet_sections', ['id' => $section->id, 'label' => 'Section de A']);
});

test("l'enseignant peut supprimer une section et les ordres sont renumérotés", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = creerCours($enseignant);
    $typeProjet = TypeProjet::create(['enseignant_id' => $enseignant->id, 'cours_id' => $cours->id, 'nom' => 'Projet', 'accessible' => false]);
    $s1 = TypeProjetSection::create(['type_projet_id' => $typeProjet->id, 'label' => 'S1', 'ordre' => 1]);
    $s2 = TypeProjetSection::create(['type_projet_id' => $typeProjet->id, 'label' => 'S2', 'ordre' => 2]);
    $s3 = TypeProjetSection::create(['type_projet_id' => $typeProjet->id, 'label' => 'S3', 'ordre' => 3]);

    $this->actingAs($enseignant)
        ->delete("/cours/{$cours->id}/types-projets/{$typeProjet->id}/sections/{$s2->id}")
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
    $classeSection = Classe::create(['cours_id' => $cours->id]);
    $classeSection->etudiants()->attach($etudiant->id);
    $classe = Groupe::create(['classe_id' => $classeSection->id, 'created_by' => $etudiant->id]);

    $typeProjet = TypeProjet::create(['enseignant_id' => $enseignant->id, 'cours_id' => $cours->id, 'nom' => 'Projet', 'accessible' => false]);
    $section = TypeProjetSection::create(['type_projet_id' => $typeProjet->id, 'label' => 'Intro', 'ordre' => 1]);

    $projet = ProjetRecherche::create(['groupe_id' => $classe->id, 'type_projet_id' => $typeProjet->id]);
    ProjetSectionContenu::create(['projet_id' => $projet->id, 'section_id' => $section->id, 'contenu' => '<p>Texte</p>']);

    $this->actingAs($enseignant)
        ->delete("/cours/{$cours->id}/types-projets/{$typeProjet->id}/sections/{$section->id}")
        ->assertRedirect();

    $this->assertDatabaseMissing('projet_section_contenus', ['section_id' => $section->id]);
});

// ─── Store avec sections ───────────────────────────────────────────────────────

test("l'enseignant peut créer un type de projet avec des sections inline", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = creerCours($enseignant);

    $this->actingAs($enseignant)
        ->post("/cours/{$cours->id}/types-projets", [
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
    $cours = creerCours($enseignant);
    $typeProjet = TypeProjet::create(['enseignant_id' => $enseignant->id, 'cours_id' => $cours->id, 'nom' => 'Projet', 'accessible' => false]);

    $this->actingAs($enseignant)
        ->put("/cours/{$cours->id}/types-projets/{$typeProjet->id}", [
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
    $classeSection = Classe::create(['cours_id' => $cours->id]);
    $classeSection->etudiants()->attach($etudiant->id);
    $classe = Groupe::create(['classe_id' => $classeSection->id, 'created_by' => $etudiant->id]);

    $typeProjet = TypeProjet::create(['enseignant_id' => $enseignant->id, 'cours_id' => $cours->id, 'nom' => 'Projet', 'accessible' => false]);
    $s1 = TypeProjetSection::create(['type_projet_id' => $typeProjet->id, 'label' => 'S1', 'ordre' => 1]);
    $s2 = TypeProjetSection::create(['type_projet_id' => $typeProjet->id, 'label' => 'S2', 'ordre' => 2]);

    $projet = ProjetRecherche::create(['groupe_id' => $classe->id, 'type_projet_id' => $typeProjet->id]);
    ProjetSectionContenu::create(['projet_id' => $projet->id, 'section_id' => $s2->id, 'contenu' => '<p>Texte</p>']);

    // Envoyer uniquement s1 — s2 doit être supprimé avec son contenu
    $this->actingAs($enseignant)
        ->put("/cours/{$cours->id}/types-projets/{$typeProjet->id}", [
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

test("l'update d'une section conserve le pointage envoyé", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = creerCours($enseignant);
    $typeProjet = TypeProjet::create(['enseignant_id' => $enseignant->id, 'cours_id' => $cours->id, 'nom' => 'Projet', 'accessible' => false]);
    $section = TypeProjetSection::create(['type_projet_id' => $typeProjet->id, 'label' => 'Intro', 'ordre' => 1]);

    $this->actingAs($enseignant)
        ->put("/cours/{$cours->id}/types-projets/{$typeProjet->id}", [
            'nom' => 'Projet',
            'sections' => [
                ['id' => $section->id, 'label' => 'Intro', 'pointage' => 15.5],
            ],
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('type_projet_sections', ['id' => $section->id, 'pointage' => 15.5]);
});

test("l'update sans clé sections ne modifie pas les sections existantes", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = creerCours($enseignant);
    $typeProjet = TypeProjet::create(['enseignant_id' => $enseignant->id, 'cours_id' => $cours->id, 'nom' => 'Projet', 'accessible' => false]);
    TypeProjetSection::create(['type_projet_id' => $typeProjet->id, 'label' => 'Intro', 'ordre' => 1]);

    $this->actingAs($enseignant)
        ->put("/cours/{$cours->id}/types-projets/{$typeProjet->id}", ['nom' => 'Nouveau nom'])
        ->assertRedirect();

    expect(TypeProjetSection::where('type_projet_id', $typeProjet->id)->count())->toBe(1);
});

// ─── Edit (page dédiée) ────────────────────────────────────────────────────────

test("l'enseignant accède à la page d'édition de son type de projet", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = creerCours($enseignant);
    $typeProjet = TypeProjet::create(['enseignant_id' => $enseignant->id, 'cours_id' => $cours->id, 'nom' => 'Projet X', 'accessible' => false]);
    TypeProjetSection::create(['type_projet_id' => $typeProjet->id, 'label' => 'Introduction', 'ordre' => 1, 'type' => 'texte']);

    $this->actingAs($enseignant)
        ->get("/cours/{$cours->id}/types-projets/{$typeProjet->id}/edit")
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
    $cours = creerCours($enseignantA);
    $typeProjet = TypeProjet::create(['enseignant_id' => $enseignantA->id, 'cours_id' => $cours->id, 'nom' => 'Projet A', 'accessible' => false]);

    $this->actingAs($enseignantB)
        ->get("/cours/{$cours->id}/types-projets/{$typeProjet->id}/edit")
        ->assertForbidden();
});

test("un étudiant est redirigé depuis la page d'édition d'un type de projet", function () {
    $etudiant = User::factory()->create(['role' => 'etudiant']);
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = creerCours($enseignant);
    $typeProjet = TypeProjet::create(['enseignant_id' => $enseignant->id, 'cours_id' => $cours->id, 'nom' => 'Projet', 'accessible' => false]);

    $this->actingAs($etudiant)
        ->get("/cours/{$cours->id}/types-projets/{$typeProjet->id}/edit")
        ->assertRedirect();
});

// ─────────────────────────────────────────────────────────────────────────────

test("l'enseignant peut réordonner les sections", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = creerCours($enseignant);
    $typeProjet = TypeProjet::create(['enseignant_id' => $enseignant->id, 'cours_id' => $cours->id, 'nom' => 'Projet', 'accessible' => false]);
    $s1 = TypeProjetSection::create(['type_projet_id' => $typeProjet->id, 'label' => 'S1', 'ordre' => 1]);
    $s2 = TypeProjetSection::create(['type_projet_id' => $typeProjet->id, 'label' => 'S2', 'ordre' => 2]);
    $s3 = TypeProjetSection::create(['type_projet_id' => $typeProjet->id, 'label' => 'S3', 'ordre' => 3]);

    $this->actingAs($enseignant)
        ->put("/cours/{$cours->id}/types-projets/{$typeProjet->id}/sections/reorder", [
            'ordre' => [$s3->id, $s1->id, $s2->id],
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('type_projet_sections', ['id' => $s3->id, 'ordre' => 1]);
    $this->assertDatabaseHas('type_projet_sections', ['id' => $s1->id, 'ordre' => 2]);
    $this->assertDatabaseHas('type_projet_sections', ['id' => $s2->id, 'ordre' => 3]);
});
