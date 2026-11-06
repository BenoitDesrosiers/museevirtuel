<?php

use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetCritereCorrection;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use App\Models\TypeProjetCritere;
use App\Models\User;
use App\Services\CorrectionCritereService;

/**
 * Projet d'un groupe de deux étudiants avec un critère positif global.
 *
 * @return array{projet: ProjetRecherche, critere: TypeProjetCritere, autreCritere: TypeProjetCritere, e1: User, e2: User}
 */
function scenarioCorrectionCritere(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = Cours::create([
        'nom_cours' => 'Histoire',
        'code' => '330-CRI',
        'groupe' => 'A',
        'enseignant_id' => $enseignant->id,
    ]);
    $typeProjet = TypeProjet::create(['enseignant_id' => $enseignant->id, 'cours_id' => $cours->id, 'nom' => 'Projet']);

    [$e1, $e2] = User::factory()->count(2)->create(['role' => 'etudiant'])->all();
    $classe = Classe::create(['cours_id' => $cours->id]);
    $groupe = Groupe::create(['classe_id' => $classe->id, 'created_by' => $e1->id]);
    $groupe->membres()->attach([$e1->id, $e2->id]);

    $projet = ProjetRecherche::create(['groupe_id' => $groupe->id, 'type_projet_id' => $typeProjet->id]);

    $attributs = ['type_projet_id' => $typeProjet->id, 'type' => 'positif', 'contenu_type' => 'texte', 'pointage' => 5.0, 'visible' => true];
    $critere = TypeProjetCritere::create([...$attributs, 'ordre' => 1]);
    $autreCritere = TypeProjetCritere::create([...$attributs, 'ordre' => 2]);

    return compact('projet', 'critere', 'autreCritere', 'e1', 'e2');
}

test('une correction de groupe remplace les corrections individuelles du même critère et retourne leurs étudiants', function () {
    ['projet' => $projet, 'critere' => $critere, 'autreCritere' => $autreCritere, 'e1' => $e1, 'e2' => $e2] = scenarioCorrectionCritere();

    foreach ([$e1, $e2] as $etudiant) {
        ProjetCritereCorrection::create(['projet_id' => $projet->id, 'critere_id' => $critere->id, 'user_id' => $etudiant->id, 'points' => 2]);
    }
    // Correction individuelle sur un autre critère : ne doit pas être touchée
    $autre = ProjetCritereCorrection::create(['projet_id' => $projet->id, 'critere_id' => $autreCritere->id, 'user_id' => $e1->id, 'points' => 1]);

    $resultat = (new CorrectionCritereService)->enregistrer($projet, $critere, ['points' => 4], true);

    expect($resultat['idsRetires'])->toEqualCanonicalizing([$e1->id, $e2->id])
        ->and($resultat['correction']->user_id)->toBeNull()
        ->and($resultat['correction']->verifie)->toBeTruthy()
        ->and(ProjetCritereCorrection::where('critere_id', $critere->id)->count())->toBe(1)
        ->and(ProjetCritereCorrection::find($autre->id))->not->toBeNull();
});

test('une correction individuelle ne retire aucune autre correction', function () {
    ['projet' => $projet, 'critere' => $critere, 'e1' => $e1, 'e2' => $e2] = scenarioCorrectionCritere();
    ProjetCritereCorrection::create(['projet_id' => $projet->id, 'critere_id' => $critere->id, 'user_id' => $e2->id, 'points' => 2]);

    $resultat = (new CorrectionCritereService)->enregistrer($projet, $critere, ['user_id' => $e1->id, 'points' => 3], false);

    expect($resultat['idsRetires'])->toBe([])
        ->and(ProjetCritereCorrection::where('critere_id', $critere->id)->count())->toBe(2);
});

test('cloner remplace la correction individuelle existante de l\'étudiant et garde la source', function () {
    ['projet' => $projet, 'critere' => $critere, 'e1' => $e1] = scenarioCorrectionCritere();
    $source = ProjetCritereCorrection::create(['projet_id' => $projet->id, 'critere_id' => $critere->id, 'user_id' => null, 'points' => 5]);
    $ancienne = ProjetCritereCorrection::create(['projet_id' => $projet->id, 'critere_id' => $critere->id, 'user_id' => $e1->id, 'points' => 1]);

    $clone = (new CorrectionCritereService)->cloner($projet, $source, ['user_id' => $e1->id, 'points' => 3], false);

    expect($clone->source_id)->toBe($source->id)
        ->and(ProjetCritereCorrection::find($ancienne->id))->toBeNull();

    // Supprimer la source supprime aussi ses clones
    (new CorrectionCritereService)->supprimer($source);

    expect(ProjetCritereCorrection::count())->toBe(0);
});
