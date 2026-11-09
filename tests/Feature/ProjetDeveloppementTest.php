<?php

use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetAnnotation;
use App\Models\ProjetDeveloppement;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use App\Models\User;

/**
 * Groupe d'un étudiant avec un projet ouvert.
 *
 * @return array{enseignant: User, etudiant: User, projet: ProjetRecherche, url: string}
 */
function scenarioDeveloppement(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $etudiant = User::factory()->create(['role' => 'etudiant']);
    $cours = Cours::create([
        'nom_cours' => 'Histoire',
        'code' => '330-DEV',
        'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);
    $classe = Classe::create(['cours_id' => $cours->id]);
    $classe->etudiants()->attach($etudiant->id);
    $groupe = Groupe::create(['classe_id' => $classe->id, 'created_by' => $etudiant->id]);
    $groupe->membres()->attach($etudiant->id);
    $typeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id,
        'cours_id' => $cours->id,
        'nom' => 'Projet',
        'accessible' => true,
    ]);
    $projet = ProjetRecherche::create(['groupe_id' => $groupe->id, 'type_projet_id' => $typeProjet->id]);

    $url = "/cours/{$cours->id}/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/developpements";

    return compact('enseignant', 'etudiant', 'projet', 'url');
}

test('un membre ajoute un développement vide à la fin de la liste', function () {
    ['etudiant' => $etudiant, 'projet' => $projet, 'url' => $url] = scenarioDeveloppement();
    $projet->developpements()->create(['ordre' => 1]);

    $this->actingAs($etudiant)
        ->postJson($url)
        ->assertCreated()
        ->assertJsonPath('developpement.ordre', 2)
        ->assertJsonPath('developpement.contenu', null);
});

test('un refus d\'édition ne crée pas de projet', function () {
    ['enseignant' => $enseignant, 'projet' => $projet, 'url' => $url] = scenarioDeveloppement();
    $projet->delete();

    // L'enseignant accède au projet mais ne peut pas écrire hors du mode édition
    $this->actingAs($enseignant)
        ->postJson($url)
        ->assertForbidden();

    $this->assertDatabaseMissing('projets_recherche', ['groupe_id' => $projet->groupe_id, 'type_projet_id' => $projet->type_projet_id]);
});

test('un membre crée le projet s\'il n\'existe pas encore', function () {
    ['etudiant' => $etudiant, 'projet' => $projet, 'url' => $url] = scenarioDeveloppement();
    $projet->delete();

    $this->actingAs($etudiant)
        ->postJson($url)
        ->assertCreated()
        ->assertJsonPath('developpement.ordre', 1);

    $this->assertDatabaseHas('projets_recherche', ['groupe_id' => $projet->groupe_id, 'type_projet_id' => $projet->type_projet_id]);
});

test('modifier le contenu supprime les annotations dont la marque a disparu', function () {
    ['enseignant' => $enseignant, 'etudiant' => $etudiant, 'projet' => $projet, 'url' => $url] = scenarioDeveloppement();
    $dev = $projet->developpements()->create(['ordre' => 1, 'contenu' => '<p><mark data-comment-id="a">x</mark></p>']);
    ProjetAnnotation::create([
        'projet_id' => $projet->id,
        'champ' => "developpement_{$dev->id}",
        'commentaire_id' => 'a',
        'contenu' => 'Note',
        'user_id' => $enseignant->id,
    ]);

    $this->actingAs($etudiant)
        ->putJson("{$url}/{$dev->id}", ['titre' => 'Titre', 'contenu' => '<p>texte sans marque</p>'])
        ->assertOk();

    expect($dev->fresh()->titre)->toBe('Titre')
        ->and(ProjetAnnotation::count())->toBe(0);
});

test('le dernier développement ne peut pas être supprimé (422)', function () {
    ['etudiant' => $etudiant, 'projet' => $projet, 'url' => $url] = scenarioDeveloppement();
    $dev = $projet->developpements()->create(['ordre' => 1]);

    $this->actingAs($etudiant)
        ->deleteJson("{$url}/{$dev->id}")
        ->assertUnprocessable()
        ->assertJsonPath('message', __('projet.developpement_minimum'));

    expect(ProjetDeveloppement::find($dev->id))->not->toBeNull();
});

test('supprimer un développement renumérote les suivants', function () {
    ['etudiant' => $etudiant, 'projet' => $projet, 'url' => $url] = scenarioDeveloppement();
    $d1 = $projet->developpements()->create(['ordre' => 1]);
    $d2 = $projet->developpements()->create(['ordre' => 2]);
    $d3 = $projet->developpements()->create(['ordre' => 3]);

    $this->actingAs($etudiant)->deleteJson("{$url}/{$d1->id}")->assertOk();

    expect($d2->fresh()->ordre)->toBe(1)
        ->and($d3->fresh()->ordre)->toBe(2);
});
