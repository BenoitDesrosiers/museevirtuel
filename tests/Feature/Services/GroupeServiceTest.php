<?php

use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\Thematique;
use App\Models\User;
use App\Services\GroupeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/**
 * Cours avec une classe de 3 étudiants inscrits et une thématique de l'enseignant.
 */
function scenarioGroupeService(array $attrsCours = []): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = Cours::create(array_merge([
        'nom_cours' => 'Histoire',
        'code' => '330-SRV',
        'groupe' => 'A',
        'enseignant_id' => $enseignant->id,
    ], $attrsCours));

    $etudiants = User::factory()->count(3)->create(['role' => 'etudiant']);
    $classe = Classe::create(['cours_id' => $cours->id]);
    $classe->etudiants()->attach($etudiants->pluck('id'));

    $thematique = Thematique::factory()->create(['enseignant_id' => $enseignant->id]);

    return compact('cours', 'classe', 'etudiants', 'thematique');
}

/**
 * Exécute l'appel et retourne les messages de la ValidationException levée.
 *
 * @return array<string, array<int, string>>
 */
function erreursDe(Closure $appel): array
{
    try {
        $appel();
    } catch (ValidationException $e) {
        return $e->errors();
    }

    throw new RuntimeException('Aucune ValidationException levée.');
}

test('creer inclut le créateur et attache les membres et thématiques', function () {
    ['cours' => $cours, 'classe' => $classe, 'etudiants' => $etudiants, 'thematique' => $thematique] = scenarioGroupeService();

    $groupe = app(GroupeService::class)->creer($etudiants[0], $cours, $classe, [$etudiants[1]->id], [$thematique->id]);

    expect($groupe->membres()->pluck('users.id')->sort()->values()->all())
        ->toBe([$etudiants[0]->id, $etudiants[1]->id])
        ->and($groupe->thematiques()->pluck('thematiques.id')->all())->toBe([$thematique->id]);
});

test('creer refuse un étudiant déjà dans un groupe de la classe', function () {
    ['cours' => $cours, 'classe' => $classe, 'etudiants' => $etudiants, 'thematique' => $thematique] = scenarioGroupeService();
    $service = app(GroupeService::class);
    $service->creer($etudiants[0], $cours, $classe, [$etudiants[1]->id], [$thematique->id]);

    $erreurs = erreursDe(fn () => $service->creer($etudiants[0], $cours, $classe, [$etudiants[2]->id], [$thematique->id]));

    expect($erreurs)->toHaveKey('general');
});

test('creer respecte la taille maximale d\'équipe du cours', function () {
    ['cours' => $cours, 'classe' => $classe, 'etudiants' => $etudiants, 'thematique' => $thematique] = scenarioGroupeService(['taille_equipe_max' => 2]);

    $erreurs = erreursDe(fn () => app(GroupeService::class)->creer(
        $etudiants[0], $cours, $classe, [$etudiants[1]->id, $etudiants[2]->id], [$thematique->id],
    ));

    expect($erreurs['membres'][0])->toBe(__('groupe.taille_max', ['max' => 2]))
        ->and(Groupe::count())->toBe(0);
});

test('creer refuse une thématique non visible par l\'enseignant du cours', function () {
    ['cours' => $cours, 'classe' => $classe, 'etudiants' => $etudiants] = scenarioGroupeService();
    $thematiqueEtrangere = Thematique::factory()->create();

    $erreurs = erreursDe(fn () => app(GroupeService::class)->creer(
        $etudiants[0], $cours, $classe, [$etudiants[1]->id], [$thematiqueEtrangere->id],
    ));

    expect($erreurs)->toHaveKey('thematiques');
});

test('modifierMembres ajoute et retire sans permettre à l\'auteur de se retirer', function () {
    ['cours' => $cours, 'classe' => $classe, 'etudiants' => $etudiants, 'thematique' => $thematique] = scenarioGroupeService();
    $service = app(GroupeService::class);
    $groupe = $service->creer($etudiants[0], $cours, $classe, [$etudiants[1]->id], [$thematique->id]);

    $service->modifierMembres($etudiants[0], $cours, $classe, $groupe, [$etudiants[2]->id], [$etudiants[0]->id, $etudiants[1]->id]);

    expect($groupe->membres()->pluck('users.id')->sort()->values()->all())
        ->toBe([$etudiants[0]->id, $etudiants[2]->id]);
});

test('modifierMembres refuse de descendre sous la taille minimale du cours', function () {
    ['cours' => $cours, 'classe' => $classe, 'etudiants' => $etudiants, 'thematique' => $thematique] = scenarioGroupeService(['taille_equipe_min' => 3]);
    $service = app(GroupeService::class);
    $groupe = $service->creer($etudiants[0], $cours, $classe, [$etudiants[1]->id, $etudiants[2]->id], [$thematique->id]);

    $erreurs = erreursDe(fn () => $service->modifierMembres($etudiants[0], $cours, $classe, $groupe, [], [$etudiants[2]->id]));

    expect($erreurs['membres'][0])->toBe(__('groupe.taille_min', ['min' => 3]))
        ->and($groupe->membres()->count())->toBe(3);
});

test('synchroniserThematiques remplace les thématiques du groupe', function () {
    ['cours' => $cours, 'classe' => $classe, 'etudiants' => $etudiants, 'thematique' => $thematique] = scenarioGroupeService();
    $service = app(GroupeService::class);
    $groupe = $service->creer($etudiants[0], $cours, $classe, [$etudiants[1]->id], [$thematique->id]);
    $autre = Thematique::factory()->create(['enseignant_id' => $cours->enseignant_id]);

    $service->synchroniserThematiques($cours, $groupe, [$autre->id]);

    expect($groupe->thematiques()->pluck('thematiques.id')->all())->toBe([$autre->id]);
});
