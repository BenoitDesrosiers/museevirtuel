<?php

use App\Actions\TransfererCoursAction;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\CoursLienEntrevue;
use App\Models\CoursObjectif;
use App\Models\EcheancierEtape;
use App\Models\GrilleCorrection;
use App\Models\GrilleCritere;
use App\Models\GrilleMalus;
use App\Models\TypeProjet;
use App\Models\TypeProjetSection;
use App\Models\TypeProjetTache;
use App\Models\User;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Crée un cours complet avec du contenu pédagogique pour tester le transfert.
 *
 * @return array{enseignant: User, cours: Cours}
 */
function scenarioTransfertCours(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    $cours = Cours::create([
        'nom_cours' => 'Cours à transférer',
        'code' => '330-TRF',
        'groupe' => '01',
        'annee' => 2026,
        'session' => 'hiver',
        'enseignant_id' => $enseignant->id,
    ]);

    // Étapes de l'échéancier
    EcheancierEtape::create([
        'cours_id' => $cours->id,
        'semaine' => 1,
        'periode' => null,
        'etape' => 'Formation des équipes',
        'is_done' => true,
        'ordre' => 1,
    ]);
    EcheancierEtape::create([
        'cours_id' => $cours->id,
        'semaine' => 2,
        'periode' => null,
        'etape' => 'Choix du sujet',
        'is_done' => false,
        'ordre' => 1,
    ]);

    // Objectifs pédagogiques
    CoursObjectif::create([
        'cours_id' => $cours->id,
        'contenu' => 'Comprendre le contexte historique',
        'ordre' => 1,
    ]);

    // Lien d'entrevue
    CoursLienEntrevue::create([
        'cours_id' => $cours->id,
        'label' => 'Guide d\'entrevue',
        'url' => 'https://example.com/guide',
        'ordre' => 1,
    ]);

    // Type de projet avec grille et sections
    $typeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id,
        'cours_id' => $cours->id,
        'nom' => 'Projet de recherche',
        'description' => 'Description test',
        'accessible' => true,
        'date_remise' => now()->addMonth(),
        'remises_multiples' => false,
        'retard_permis' => true,
        'generer_page_titre' => true,
        'generer_table_matieres' => false,
        'ponderation' => '60.00',
        'is_sommatif' => true,
    ]);

    $grille = GrilleCorrection::create([
        'type_projet_id' => $typeProjet->id,
        'nom' => 'Grille principale',
        'description' => 'Description grille',
    ]);

    GrilleCritere::create(['grille_id' => $grille->id, 'label' => 'Contenu', 'ponderation' => 50, 'ordre' => 1]);
    GrilleCritere::create(['grille_id' => $grille->id, 'label' => 'Forme', 'ponderation' => 30, 'ordre' => 2]);
    GrilleMalus::create(['grille_id' => $grille->id, 'label' => 'Retard', 'deduction' => 10, 'description' => '-10%', 'ordre' => 1]);

    TypeProjetSection::create(['type_projet_id' => $typeProjet->id, 'label' => 'Introduction', 'description' => null, 'ordre' => 1, 'type' => 'introduction']);
    TypeProjetSection::create(['type_projet_id' => $typeProjet->id, 'label' => 'Développement', 'description' => null, 'ordre' => 2, 'type' => 'developpement']);
    TypeProjetTache::create(['type_projet_id' => $typeProjet->id, 'titre' => 'Rédiger le plan', 'description' => null, 'ordre' => 1]);

    // Classe avec un étudiant (ne doit PAS être copié)
    $etudiant = User::factory()->create(['role' => 'etudiant']);
    $classe = Classe::create(['cours_id' => $cours->id]);
    $classe->etudiants()->attach($etudiant->id);

    return compact('enseignant', 'cours');
}

// ─── TransfererCoursAction ────────────────────────────────────────────────────

test("l'action copie l'echeancier en remettant is_done a false", function () {
    ['cours' => $cours] = scenarioTransfertCours();

    $nouveau = app(TransfererCoursAction::class)->execute($cours, 2027, 'automne');

    expect($nouveau->echeancierEtapes)->toHaveCount(2);
    expect($nouveau->echeancierEtapes->every(fn ($e) => $e->is_done === false))->toBeTrue();
});

test("l'action copie les objectifs pedagogiques", function () {
    ['cours' => $cours] = scenarioTransfertCours();

    $nouveau = app(TransfererCoursAction::class)->execute($cours, 2027, 'automne');

    expect($nouveau->objectifs)->toHaveCount(1);
    expect($nouveau->objectifs->first()->contenu)->toBe('Comprendre le contexte historique');
});

test("l'action copie les liens d'entrevue", function () {
    ['cours' => $cours] = scenarioTransfertCours();

    $nouveau = app(TransfererCoursAction::class)->execute($cours, 2027, 'automne');

    expect($nouveau->liensEntrevue)->toHaveCount(1);
    expect($nouveau->liensEntrevue->first()->url)->toBe('https://example.com/guide');
});

test("l'action copie les types de projets avec grille et sections", function () {
    ['cours' => $cours] = scenarioTransfertCours();

    $nouveau = app(TransfererCoursAction::class)->execute($cours, 2027, 'automne');

    $typesProjets = $nouveau->typesProjets()->with(['grille.criteres', 'grille.malus', 'sections', 'taches'])->get();

    expect($typesProjets)->toHaveCount(1);

    $tp = $typesProjets->first();
    expect($tp->grille->criteres)->toHaveCount(2);
    expect($tp->grille->malus)->toHaveCount(1);
    expect($tp->sections)->toHaveCount(2);
    expect($tp->taches)->toHaveCount(1);
});

test("l'action reinitialise accessible et date_remise sur les types de projets", function () {
    ['cours' => $cours] = scenarioTransfertCours();

    $nouveau = app(TransfererCoursAction::class)->execute($cours, 2027, 'automne');

    $tp = $nouveau->typesProjets()->first();
    expect($tp->accessible)->toBeFalse();
    expect($tp->date_remise)->toBeNull();
});

test("l'action ne copie pas les classes ni les etudiants", function () {
    ['cours' => $cours] = scenarioTransfertCours();

    $nouveau = app(TransfererCoursAction::class)->execute($cours, 2027, 'automne');

    expect($nouveau->classes)->toHaveCount(0);
});

test('le nouveau cours a la bonne annee et session', function () {
    ['cours' => $cours] = scenarioTransfertCours();

    $nouveau = app(TransfererCoursAction::class)->execute($cours, 2027, 'ete');

    expect($nouveau->annee)->toBe(2027);
    expect($nouveau->session->value)->toBe('ete');
    expect($nouveau->is_verrouille)->toBeFalse();
});

test('le nouveau cours conserve les metadonnees du cours source', function () {
    ['cours' => $cours] = scenarioTransfertCours();

    $nouveau = app(TransfererCoursAction::class)->execute($cours, 2027, 'automne');

    expect($nouveau->nom_cours)->toBe($cours->nom_cours);
    expect($nouveau->code)->toBe($cours->code);
    expect($nouveau->enseignant_id)->toBe($cours->enseignant_id);
});

// ─── TransfererCoursController ────────────────────────────────────────────────

test("l'enseignant peut transférer son cours via le controller", function () {
    ['enseignant' => $enseignant, 'cours' => $cours] = scenarioTransfertCours();

    $this->actingAs($enseignant)
        ->post("/cours/{$cours->id}/transferer", [
            'annee' => 2027,
            'session' => 'automne',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('cours', [
        'code' => $cours->code,
        'annee' => 2027,
        'session' => 'automne',
        'enseignant_id' => $enseignant->id,
    ]);
});

test('un autre enseignant ne peut pas transferer le cours', function () {
    ['cours' => $cours] = scenarioTransfertCours();
    $autreEnseignant = User::factory()->create(['role' => 'enseignant']);

    $this->actingAs($autreEnseignant)
        ->post("/cours/{$cours->id}/transferer", [
            'annee' => 2027,
            'session' => 'automne',
        ])
        ->assertForbidden();
});

test('le transfert avec session invalide echoue la validation', function () {
    ['enseignant' => $enseignant, 'cours' => $cours] = scenarioTransfertCours();

    $this->actingAs($enseignant)
        ->post("/cours/{$cours->id}/transferer", [
            'annee' => 2027,
            'session' => 'printemps',
        ])
        ->assertSessionHasErrors('session');
});
