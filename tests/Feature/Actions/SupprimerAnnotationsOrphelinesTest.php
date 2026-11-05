<?php

use App\Actions\SupprimerAnnotationsOrphelines;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetAnnotation;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use App\Models\User;

/**
 * Crée un projet de recherche minimal.
 */
function creerProjetPourAnnotations(): ProjetRecherche
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = Cours::create([
        'nom_cours' => 'Histoire',
        'code' => '330-ANN',
        'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);
    $classe = Classe::create(['cours_id' => $cours->id]);
    $groupe = Groupe::create(['classe_id' => $classe->id, 'created_by' => $enseignant->id]);
    $typeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id,
        'cours_id' => $cours->id,
        'nom' => 'Projet',
    ]);

    return ProjetRecherche::create(['groupe_id' => $groupe->id, 'type_projet_id' => $typeProjet->id]);
}

/**
 * Crée une annotation sur un champ du projet.
 */
function annoter(ProjetRecherche $projet, string $champ, string $commentaireId): ProjetAnnotation
{
    return ProjetAnnotation::create([
        'projet_id' => $projet->id,
        'champ' => $champ,
        'commentaire_id' => $commentaireId,
        'contenu' => 'Commentaire',
        'user_id' => $projet->typeProjet->enseignant_id,
    ]);
}

test('supprime les annotations dont la marque a disparu et garde les autres', function () {
    $projet = creerProjetPourAnnotations();
    $gardee = annoter($projet, 'section_1', 'a-garder');
    $orpheline = annoter($projet, 'section_1', 'orpheline');

    $html = '<p>Texte <mark data-comment-id="a-garder">annoté</mark></p>';
    $supprimees = (new SupprimerAnnotationsOrphelines)->execute($projet, 'section_1', $html);

    expect($supprimees)->toBe(1)
        ->and(ProjetAnnotation::find($gardee->id))->not->toBeNull()
        ->and(ProjetAnnotation::find($orpheline->id))->toBeNull();
});

test('supprime toutes les annotations du champ quand le HTML ne contient plus de marque', function () {
    $projet = creerProjetPourAnnotations();
    annoter($projet, 'section_1', 'un');
    annoter($projet, 'section_1', 'deux');

    $supprimees = (new SupprimerAnnotationsOrphelines)->execute($projet, 'section_1', '<p>Plus rien</p>');

    expect($supprimees)->toBe(2)
        ->and(ProjetAnnotation::count())->toBe(0);
});

test('ne touche ni aux autres champs ni aux autres projets', function () {
    $projet = creerProjetPourAnnotations();
    $autreProjet = creerProjetPourAnnotations();
    $autreChamp = annoter($projet, 'section_2', 'x');
    $autreProjetAnnotation = annoter($autreProjet, 'section_1', 'y');

    (new SupprimerAnnotationsOrphelines)->execute($projet, 'section_1', '<p></p>');

    expect(ProjetAnnotation::find($autreChamp->id))->not->toBeNull()
        ->and(ProjetAnnotation::find($autreProjetAnnotation->id))->not->toBeNull();
});
