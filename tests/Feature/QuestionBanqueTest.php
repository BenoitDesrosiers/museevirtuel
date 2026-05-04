<?php

use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetQuestionChoisie;
use App\Models\ProjetRecherche;
use App\Models\QuestionBanque;
use App\Models\TypeProjet;
use App\Models\TypeProjetSection;
use App\Models\User;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Crée un scénario complet : enseignant, cours, classe, groupe avec étudiant,
 * typeProjet avec une section choix_questions et un projet associé.
 *
 * @return array{enseignant: User, etudiant: User, cours: Cours, classeSection: Classe, groupe: Groupe, typeProjet: TypeProjet, section: TypeProjetSection, projet: ProjetRecherche}
 */
function creerScenarioQuestions(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $etudiant = User::factory()->create(['role' => 'etudiant']);

    $cours = Cours::create([
        'nom_cours' => 'Histoire',
        'description' => 'Test',
        'code' => '330-QST',
        'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);

    $classeSection = Classe::create(['cours_id' => $cours->id]);
    $classeSection->etudiants()->attach($etudiant->id);

    $groupe = Groupe::create(['classe_id' => $classeSection->id, 'created_by' => $etudiant->id]);
    $groupe->membres()->attach($etudiant->id);

    $typeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id,
        'cours_id' => $cours->id,
        'nom' => 'Construction de questions',
        'accessible' => true,
    ]);

    $section = TypeProjetSection::create([
        'type_projet_id' => $typeProjet->id,
        'label' => 'Choix des questions',
        'type' => 'choix_questions',
        'ordre' => 1,
    ]);

    $projet = ProjetRecherche::create([
        'groupe_id' => $groupe->id,
        'type_projet_id' => $typeProjet->id,
    ]);

    return compact('enseignant', 'etudiant', 'cours', 'classeSection', 'groupe', 'typeProjet', 'section', 'projet');
}

/** URL CRUD enseignant pour la banque de questions. */
function urlQuestions(Cours $cours, TypeProjet $typeProjet, TypeProjetSection $section): string
{
    return "/cours/{$cours->id}/types-projets/{$typeProjet->id}/sections/{$section->id}/questions";
}

/** URL choisir pour l'étudiant. */
function urlChoisir(Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, TypeProjetSection $section): string
{
    return "/cours/{$cours->id}/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/sections/{$section->id}/questions/choisir";
}

// ─── store() — Enseignant ──────────────────────────────────────────────────────

test("l'enseignant peut ajouter une question à la banque", function () {
    $s = creerScenarioQuestions();

    $this->actingAs($s['enseignant'])
        ->post(urlQuestions($s['cours'], $s['typeProjet'], $s['section']), [
            'contenu' => 'Où étiez-vous au début de la guerre ?',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('question_banques', [
        'section_id' => $s['section']->id,
        'contenu' => 'Où étiez-vous au début de la guerre ?',
    ]);
});

test("l'ordre est automatiquement incrémenté lors de l'ajout", function () {
    $s = creerScenarioQuestions();

    $this->actingAs($s['enseignant'])
        ->post(urlQuestions($s['cours'], $s['typeProjet'], $s['section']), ['contenu' => 'Question A']);

    $this->actingAs($s['enseignant'])
        ->post(urlQuestions($s['cours'], $s['typeProjet'], $s['section']), ['contenu' => 'Question B']);

    $questions = QuestionBanque::where('section_id', $s['section']->id)->orderBy('ordre')->get();
    expect($questions[0]->contenu)->toBe('Question A');
    expect($questions[0]->ordre)->toBe(1);
    expect($questions[1]->contenu)->toBe('Question B');
    expect($questions[1]->ordre)->toBe(2);
});

test("store retourne 422 si la section n'est pas de type choix_questions", function () {
    $s = creerScenarioQuestions();

    $sectionTexte = TypeProjetSection::create([
        'type_projet_id' => $s['typeProjet']->id,
        'label' => 'Introduction',
        'type' => 'texte',
        'ordre' => 2,
    ]);

    $this->actingAs($s['enseignant'])
        ->post(urlQuestions($s['cours'], $s['typeProjet'], $sectionTexte), [
            'contenu' => 'Question invalide',
        ])
        ->assertStatus(422);
});

test("un enseignant d'un autre cours ne peut pas ajouter de questions", function () {
    $s = creerScenarioQuestions();

    $autreEnseignant = User::factory()->create(['role' => 'enseignant']);

    $this->actingAs($autreEnseignant)
        ->post(urlQuestions($s['cours'], $s['typeProjet'], $s['section']), [
            'contenu' => 'Question intruse',
        ])
        ->assertForbidden();
});

// ─── update() — Enseignant ────────────────────────────────────────────────────

test("l'enseignant peut modifier le contenu d'une question", function () {
    $s = creerScenarioQuestions();

    $question = QuestionBanque::create([
        'section_id' => $s['section']->id,
        'contenu' => 'Ancienne question',
        'ordre' => 1,
    ]);

    $this->actingAs($s['enseignant'])
        ->put(urlQuestions($s['cours'], $s['typeProjet'], $s['section'])."/{$question->id}", [
            'contenu' => 'Nouvelle formulation',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('question_banques', [
        'id' => $question->id,
        'contenu' => 'Nouvelle formulation',
    ]);
});

// ─── destroy() — Enseignant ───────────────────────────────────────────────────

test("l'enseignant peut supprimer une question", function () {
    $s = creerScenarioQuestions();

    $question = QuestionBanque::create([
        'section_id' => $s['section']->id,
        'contenu' => 'À supprimer',
        'ordre' => 1,
    ]);

    $this->actingAs($s['enseignant'])
        ->delete(urlQuestions($s['cours'], $s['typeProjet'], $s['section'])."/{$question->id}")
        ->assertRedirect();

    $this->assertDatabaseMissing('question_banques', ['id' => $question->id]);
});

test('la suppression renumérotre les questions restantes', function () {
    $s = creerScenarioQuestions();

    $q1 = QuestionBanque::create(['section_id' => $s['section']->id, 'contenu' => 'Q1', 'ordre' => 1]);
    $q2 = QuestionBanque::create(['section_id' => $s['section']->id, 'contenu' => 'Q2', 'ordre' => 2]);
    $q3 = QuestionBanque::create(['section_id' => $s['section']->id, 'contenu' => 'Q3', 'ordre' => 3]);

    $this->actingAs($s['enseignant'])
        ->delete(urlQuestions($s['cours'], $s['typeProjet'], $s['section'])."/{$q1->id}");

    expect($q2->fresh()->ordre)->toBe(1);
    expect($q3->fresh()->ordre)->toBe(2);
});

// ─── reorder() — Enseignant ───────────────────────────────────────────────────

test("l'enseignant peut réordonner les questions", function () {
    $s = creerScenarioQuestions();

    $q1 = QuestionBanque::create(['section_id' => $s['section']->id, 'contenu' => 'Q1', 'ordre' => 1]);
    $q2 = QuestionBanque::create(['section_id' => $s['section']->id, 'contenu' => 'Q2', 'ordre' => 2]);

    $this->actingAs($s['enseignant'])
        ->patch(urlQuestions($s['cours'], $s['typeProjet'], $s['section']).'/reorder', [
            'ordre' => [$q2->id, $q1->id],
        ])
        ->assertRedirect();

    expect($q2->fresh()->ordre)->toBe(1);
    expect($q1->fresh()->ordre)->toBe(2);
});

// ─── choisir() — Étudiant ─────────────────────────────────────────────────────

test('un étudiant membre peut enregistrer ses choix de questions', function () {
    $s = creerScenarioQuestions();

    $q1 = QuestionBanque::create(['section_id' => $s['section']->id, 'contenu' => 'Q1', 'ordre' => 1]);
    $q2 = QuestionBanque::create(['section_id' => $s['section']->id, 'contenu' => 'Q2', 'ordre' => 2]);

    $this->actingAs($s['etudiant'])
        ->post(urlChoisir($s['cours'], $s['classeSection'], $s['groupe'], $s['typeProjet'], $s['section']), [
            'question_ids' => [$q1->id, $q2->id],
        ])
        ->assertRedirect();

    expect(ProjetQuestionChoisie::where('projet_id', $s['projet']->id)->count())->toBe(2);
});

test('choisir remplace les anciens choix par les nouveaux', function () {
    $s = creerScenarioQuestions();

    $q1 = QuestionBanque::create(['section_id' => $s['section']->id, 'contenu' => 'Q1', 'ordre' => 1]);
    $q2 = QuestionBanque::create(['section_id' => $s['section']->id, 'contenu' => 'Q2', 'ordre' => 2]);
    $q3 = QuestionBanque::create(['section_id' => $s['section']->id, 'contenu' => 'Q3', 'ordre' => 3]);

    // Premier choix : Q1 + Q2
    $this->actingAs($s['etudiant'])
        ->post(urlChoisir($s['cours'], $s['classeSection'], $s['groupe'], $s['typeProjet'], $s['section']), [
            'question_ids' => [$q1->id, $q2->id],
        ]);

    // Nouveau choix : Q3 seulement
    $this->actingAs($s['etudiant'])
        ->post(urlChoisir($s['cours'], $s['classeSection'], $s['groupe'], $s['typeProjet'], $s['section']), [
            'question_ids' => [$q3->id],
        ]);

    expect(ProjetQuestionChoisie::where('projet_id', $s['projet']->id)->count())->toBe(1);
    $this->assertDatabaseHas('projet_questions_choisies', [
        'projet_id' => $s['projet']->id,
        'question_banque_id' => $q3->id,
    ]);
});

test('choisir avec un tableau vide supprime tous les choix', function () {
    $s = creerScenarioQuestions();

    $q1 = QuestionBanque::create(['section_id' => $s['section']->id, 'contenu' => 'Q1', 'ordre' => 1]);

    ProjetQuestionChoisie::create([
        'projet_id' => $s['projet']->id,
        'section_id' => $s['section']->id,
        'question_banque_id' => $q1->id,
    ]);

    $this->actingAs($s['etudiant'])
        ->post(urlChoisir($s['cours'], $s['classeSection'], $s['groupe'], $s['typeProjet'], $s['section']), [
            'question_ids' => [],
        ])
        ->assertRedirect();

    expect(ProjetQuestionChoisie::where('projet_id', $s['projet']->id)->count())->toBe(0);
});

test('un étudiant non-membre du groupe ne peut pas choisir des questions', function () {
    $s = creerScenarioQuestions();
    $intrus = User::factory()->create(['role' => 'etudiant']);

    $this->actingAs($intrus)
        ->post(urlChoisir($s['cours'], $s['classeSection'], $s['groupe'], $s['typeProjet'], $s['section']), [
            'question_ids' => [],
        ])
        ->assertForbidden();
});

test("choisir rejette les questions n'appartenant pas à la section", function () {
    $s = creerScenarioQuestions();

    $autreSection = TypeProjetSection::create([
        'type_projet_id' => $s['typeProjet']->id,
        'label' => 'Autre section',
        'type' => 'choix_questions',
        'ordre' => 2,
    ]);

    $questionAutreSection = QuestionBanque::create([
        'section_id' => $autreSection->id,
        'contenu' => 'Question d\'une autre section',
        'ordre' => 1,
    ]);

    $this->actingAs($s['etudiant'])
        ->post(urlChoisir($s['cours'], $s['classeSection'], $s['groupe'], $s['typeProjet'], $s['section']), [
            'question_ids' => [$questionAutreSection->id],
        ])
        ->assertStatus(422);
});
