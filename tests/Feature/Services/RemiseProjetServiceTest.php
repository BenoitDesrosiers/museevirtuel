<?php

use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use App\Models\User;
use App\Services\RemiseProjetService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Groupe de deux étudiants avec un projet sur un type de projet du cours.
 */
function scenarioRemiseService(array $attrsTypeProjet = []): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = Cours::create([
        'nom_cours' => 'Histoire',
        'code' => '330-REM',
        'groupe' => 'A',
        'enseignant_id' => $enseignant->id,
    ]);
    $typeProjet = TypeProjet::create(array_merge([
        'enseignant_id' => $enseignant->id,
        'cours_id' => $cours->id,
        'nom' => 'Projet',
        'accessible' => true,
    ], $attrsTypeProjet));

    [$e1, $e2] = User::factory()->count(2)->create(['role' => 'etudiant'])->all();
    $classe = Classe::create(['cours_id' => $cours->id]);
    $groupe = Groupe::create(['classe_id' => $classe->id, 'created_by' => $e1->id]);
    $groupe->membres()->attach([$e1->id, $e2->id]);
    $groupe->load('membres');

    $projet = ProjetRecherche::create(['groupe_id' => $groupe->id, 'type_projet_id' => $typeProjet->id]);
    $projet->setRelation('typeProjet', $typeProjet);

    return compact('groupe', 'projet', 'e1', 'e2');
}

test('voter ne remet pas le projet tant que tous les membres n\'ont pas voté pour', function () {
    ['groupe' => $groupe, 'projet' => $projet, 'e1' => $e1, 'e2' => $e2] = scenarioRemiseService();
    $service = app(RemiseProjetService::class);

    $service->voter($projet, $groupe, $e1, true);
    $service->voter($projet, $groupe, $e2, false);

    expect($projet->fresh()->remis_le)->toBeNull();
});

test('voter remet le projet quand tous les membres votent pour', function () {
    ['groupe' => $groupe, 'projet' => $projet, 'e1' => $e1, 'e2' => $e2] = scenarioRemiseService();
    $service = app(RemiseProjetService::class);

    $service->voter($projet, $groupe, $e1, true);
    $service->voter($projet, $groupe, $e2, true);

    expect($projet->fresh()->remis_le)->not->toBeNull();
});

test('voter ne re-remet pas un projet déjà remis sans remises multiples', function () {
    ['groupe' => $groupe, 'projet' => $projet, 'e1' => $e1, 'e2' => $e2] = scenarioRemiseService(['remises_multiples' => false]);
    $premiereRemise = now()->subDay()->startOfSecond();
    $projet->update(['remis_le' => $premiereRemise]);
    $service = app(RemiseProjetService::class);

    $service->voter($projet, $groupe, $e1, true);
    $service->voter($projet, $groupe, $e2, true);

    expect($projet->fresh()->remis_le->equalTo($premiereRemise))->toBeTrue();
});

test('annuler efface les votes et la date de remise', function () {
    ['groupe' => $groupe, 'projet' => $projet, 'e1' => $e1, 'e2' => $e2] = scenarioRemiseService();
    $service = app(RemiseProjetService::class);
    $service->voter($projet, $groupe, $e1, true);
    $service->voter($projet, $groupe, $e2, true);

    $service->annuler($projet->fresh()->setRelation('typeProjet', $projet->typeProjet));

    expect($projet->fresh()->remis_le)->toBeNull()
        ->and($projet->votes()->count())->toBe(0);
});
