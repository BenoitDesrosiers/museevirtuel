<?php

use App\Exceptions\TransactionEchoueeException;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\GroupeNote;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use App\Models\TypeProjetCritere;
use App\Models\TypeProjetSection;
use App\Models\User;
use App\Services\CorrectionCritereService;
use App\Services\CorrectionNoteService;
use App\Services\OrdreElements;
use App\Services\RemiseProjetService;
use Illuminate\Database\QueryException;

/**
 * Cours, classe et groupe d'un étudiant, avec une note écrite par lui.
 *
 * @return array{enseignant: User, cours: Cours, classe: Classe, groupe: Groupe, note: GroupeNote}
 */
function scenarioTransaction(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $etudiant = User::factory()->create(['role' => 'etudiant']);
    $cours = Cours::create([
        'nom_cours' => 'Histoire',
        'code' => '330-TRX',
        'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);
    $classe = Classe::create(['cours_id' => $cours->id]);
    $groupe = Groupe::create(['classe_id' => $classe->id, 'created_by' => $etudiant->id]);
    $groupe->membres()->attach($etudiant->id);
    $note = GroupeNote::create(['groupe_id' => $groupe->id, 'user_id' => $etudiant->id, 'contenu' => '<p>Original</p>']);

    return compact('enseignant', 'cours', 'classe', 'groupe', 'note');
}

// ─── Service : la transaction échoue, tout est annulé ─────────────────────────

test('un échec en base devient une TransactionEchoueeException et annule toutes les écritures', function () {
    ['note' => $note] = scenarioTransaction();

    // Auteur absent de la base : la clé étrangère user_id échoue APRÈS la mise à jour de la note
    $auteurFantome = User::factory()->make();
    $auteurFantome->id = 999999;

    $donnees = ['note_html' => '<p>Modifiée</p>', 'commentaire_id' => 'c1', 'contenu' => 'Correction'];

    try {
        (new CorrectionNoteService)->enregistrer($note, $donnees, $auteurFantome);
        $this->fail('Une TransactionEchoueeException était attendue.');
    } catch (TransactionEchoueeException $e) {
        expect($e->getMessage())->toBe(__('transaction.echec'))
            ->and($e->getPrevious())->toBeInstanceOf(QueryException::class);
    }

    expect($note->fresh()->contenu)->toBe('<p>Original</p>');
});

// ─── Contrôleurs : le message de la transaction ratée est renvoyé ─────────────

test('un contrôleur JSON renvoie le message de la transaction ratée avec un statut 500', function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe] = scenarioTransaction();
    $typeProjet = TypeProjet::create(['enseignant_id' => $enseignant->id, 'cours_id' => $cours->id, 'nom' => 'Projet']);
    $critere = TypeProjetCritere::create([
        'type_projet_id' => $typeProjet->id,
        'type' => 'positif',
        'contenu_type' => 'texte',
        'pointage' => 5,
        'visible' => true,
        'ordre' => 1,
    ]);

    $this->mock(CorrectionCritereService::class)
        ->shouldReceive('enregistrer')
        ->andThrow(new TransactionEchoueeException(__('transaction.echec')));

    $this->actingAs($enseignant)
        ->putJson("/cours/{$cours->id}/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/criteres/{$critere->id}/correction", [
            'verifie' => true,
        ])
        ->assertStatus(500)
        ->assertJsonPath('message', __('transaction.echec'));
});

test('un contrôleur de formulaire redirige avec le message de la transaction ratée', function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe, 'note' => $note] = scenarioTransaction();

    $this->mock(CorrectionNoteService::class)
        ->shouldReceive('enregistrer')
        ->andThrow(new TransactionEchoueeException(__('transaction.echec')));

    $this->actingAs($enseignant)
        ->from("/cours/{$cours->id}/classes/{$classe->id}/groupes/{$groupe->id}")
        ->put("/cours/{$cours->id}/classes/{$classe->id}/groupes/{$groupe->id}/notes/{$note->id}/corrections", [
            'commentaire_id' => 'c1',
            'contenu' => 'Correction',
            'note_html' => '<p>Modifiée</p>',
        ])
        ->assertRedirect("/cours/{$cours->id}/classes/{$classe->id}/groupes/{$groupe->id}")
        ->assertSessionHas('error', __('transaction.echec'));
});

test("un échec d'OrdreElements pendant une réorganisation est renvoyé au formulaire", function () {
    ['enseignant' => $enseignant, 'cours' => $cours] = scenarioTransaction();
    $typeProjet = TypeProjet::create(['enseignant_id' => $enseignant->id, 'cours_id' => $cours->id, 'nom' => 'Projet']);
    $s1 = TypeProjetSection::create(['type_projet_id' => $typeProjet->id, 'label' => 'A', 'type' => 'texte', 'ordre' => 1]);
    $s2 = TypeProjetSection::create(['type_projet_id' => $typeProjet->id, 'label' => 'B', 'type' => 'texte', 'ordre' => 2]);

    $this->mock(OrdreElements::class)
        ->shouldReceive('reordonner')
        ->andThrow(new TransactionEchoueeException(__('transaction.echec')));

    $this->actingAs($enseignant)
        ->from("/cours/{$cours->id}/types-projets/{$typeProjet->id}/edit")
        ->put("/cours/{$cours->id}/types-projets/{$typeProjet->id}/sections/reorder", ['ordre' => [$s2->id, $s1->id]])
        ->assertRedirect("/cours/{$cours->id}/types-projets/{$typeProjet->id}/edit")
        ->assertSessionHas('error', __('transaction.echec'));
});

test("un échec de l'annulation d'une remise est renvoyé en JSON avec un statut 500", function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe] = scenarioTransaction();
    $typeProjet = TypeProjet::create(['enseignant_id' => $enseignant->id, 'cours_id' => $cours->id, 'nom' => 'Projet']);
    ProjetRecherche::create(['groupe_id' => $groupe->id, 'type_projet_id' => $typeProjet->id, 'remis_le' => now()]);

    $this->mock(RemiseProjetService::class)
        ->shouldReceive('annuler')
        ->andThrow(new TransactionEchoueeException(__('transaction.echec')));

    $this->actingAs($enseignant)
        ->deleteJson("/cours/{$cours->id}/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/annuler-remise")
        ->assertStatus(500)
        ->assertJsonPath('message', __('transaction.echec'));
});
