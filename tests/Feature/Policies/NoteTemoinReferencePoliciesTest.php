<?php

use App\Models\Classe;
use App\Models\Cours;
use App\Models\EtudiantReference;
use App\Models\Groupe;
use App\Models\GroupeNote;
use App\Models\User;

// ─── GroupeNotePolicy ─────────────────────────────────────────────────────────

/**
 * Crée un cours, un groupe de deux membres et une note écrite par le premier.
 *
 * @return array{enseignant: User, auteur: User, coequipier: User, cours: Cours, classe: Classe, groupe: Groupe, note: GroupeNote}
 */
function creerScenarioNote(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $auteur = User::factory()->create(['role' => 'etudiant']);
    $coequipier = User::factory()->create(['role' => 'etudiant']);

    $cours = Cours::create([
        'nom_cours' => 'Histoire',
        'code' => '330-NOT',
        'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);
    $classe = Classe::create(['cours_id' => $cours->id]);
    $groupe = Groupe::create(['classe_id' => $classe->id, 'created_by' => $auteur->id]);
    $groupe->membres()->attach([$auteur->id, $coequipier->id]);

    $note = GroupeNote::create(['groupe_id' => $groupe->id, 'user_id' => $auteur->id, 'contenu' => '<p>Note</p>']);

    return compact('enseignant', 'auteur', 'coequipier', 'cours', 'classe', 'groupe', 'note');
}

test("GroupeNotePolicy::delete : seul l'auteur peut supprimer sa note", function () {
    $s = creerScenarioNote();

    expect($s['auteur']->can('delete', $s['note']))->toBeTrue()
        ->and($s['coequipier']->can('delete', $s['note']))->toBeFalse()
        ->and($s['enseignant']->can('delete', $s['note']))->toBeFalse();
});

test("GroupeNotePolicy::corriger : l'enseignant du cours et l'admin seulement", function () {
    $s = creerScenarioNote();
    $autreEnseignant = User::factory()->create(['role' => 'enseignant']);

    expect($s['enseignant']->can('corriger', $s['note']))->toBeTrue()
        ->and(User::factory()->create(['role' => 'admin'])->can('corriger', $s['note']))->toBeTrue()
        ->and($autreEnseignant->can('corriger', $s['note']))->toBeFalse()
        ->and($s['auteur']->can('corriger', $s['note']))->toBeFalse();
});

test("un coéquipier ne peut pas supprimer la note d'un autre membre (HTTP)", function () {
    $s = creerScenarioNote();

    $this->actingAs($s['coequipier'])
        ->delete("/cours/{$s['cours']->id}/classes/{$s['classe']->id}/groupes/{$s['groupe']->id}/notes/{$s['note']->id}")
        ->assertForbidden();

    expect(GroupeNote::find($s['note']->id))->not->toBeNull();
});

test("l'enseignant d'un autre cours ne peut pas corriger une note (HTTP)", function () {
    $s = creerScenarioNote();
    $autreEnseignant = User::factory()->create(['role' => 'enseignant']);

    $this->actingAs($autreEnseignant)
        ->put("/cours/{$s['cours']->id}/classes/{$s['classe']->id}/groupes/{$s['groupe']->id}/notes/{$s['note']->id}/corrections", [
            'commentaire_id' => 'c1',
            'contenu' => 'Correction',
            'note_html' => '<p>Note</p>',
        ])
        ->assertForbidden();
});

// ─── EtudiantReferencePolicy ──────────────────────────────────────────────────

test('EtudiantReferencePolicy::delete : réservé au propriétaire de la référence', function () {
    $proprietaire = User::factory()->create(['role' => 'etudiant']);
    $reference = EtudiantReference::create(['user_id' => $proprietaire->id, 'titre' => 'Source', 'ordre' => 1]);

    expect($proprietaire->can('delete', $reference))->toBeTrue()
        ->and(User::factory()->create(['role' => 'etudiant'])->can('delete', $reference))->toBeFalse();
});

// ─── UserPolicy (témoins) ─────────────────────────────────────────────────────

test("UserPolicy : on ne gère comme témoin qu'une personne âgée", function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $etudiant = User::factory()->create(['role' => 'etudiant', 'statut' => 'en_attente']);

    expect($admin->can('voirTemoin', $etudiant))->toBeFalse()
        ->and($admin->can('approuverTemoin', $etudiant))->toBeFalse()
        ->and($admin->can('declinerTemoin', $etudiant))->toBeFalse();
});

test("UserPolicy : l'admin approuve ou décline un témoin en attente, pas un témoin déjà traité", function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $enAttente = User::factory()->create(['role' => 'personne_agee', 'statut' => 'en_attente']);
    $actif = User::factory()->create(['role' => 'personne_agee', 'statut' => 'actif']);

    expect($admin->can('approuverTemoin', $enAttente))->toBeTrue()
        ->and($admin->can('declinerTemoin', $enAttente))->toBeTrue()
        ->and($admin->can('approuverTemoin', $actif))->toBeFalse();
});

test('UserPolicy : un enseignant sans thématique commune ne voit pas la fiche du témoin', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $temoin = User::factory()->create(['role' => 'personne_agee', 'statut' => 'en_attente']);

    expect($enseignant->can('voirTemoin', $temoin))->toBeFalse()
        ->and($enseignant->can('approuverTemoin', $temoin))->toBeFalse();
});

test('UserPolicy::desapprouverTemoin : seul celui qui a approuvé le témoin peut revenir dessus', function () {
    $approbateur = User::factory()->create(['role' => 'enseignant']);
    $autre = User::factory()->create(['role' => 'enseignant']);
    $temoin = User::factory()->create([
        'role' => 'personne_agee',
        'statut' => 'actif',
        'approuve_par_id' => $approbateur->id,
    ]);

    expect($approbateur->can('desapprouverTemoin', $temoin))->toBeTrue()
        ->and($autre->can('desapprouverTemoin', $temoin))->toBeFalse();

    $temoin->update(['statut' => 'en_attente']);

    expect($approbateur->can('desapprouverTemoin', $temoin->fresh()))->toBeFalse();
});
