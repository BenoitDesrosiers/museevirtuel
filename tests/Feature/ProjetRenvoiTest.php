<?php

use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetRecherche;
use App\Models\ProjetRenvoi;
use App\Models\ProjetRenvoiCommentaire;
use App\Models\TypeProjet;
use App\Models\User;
use Illuminate\Database\QueryException;

// ─── Helper ───────────────────────────────────────────────────────────────────

function creerScenarioRenvoi(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    $typeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id,
        'nom' => 'Projet renvoi test',
        'accessible' => true,
    ]);

    $cours = Cours::create([
        'nom_cours' => 'Cours test renvoi',
        'description' => 'Test',
        'heures_par_semaine' => 3,
        'code' => '330-RV1',
        'groupe' => '0001',
        'enseignant_id' => $enseignant->id,
    ]);

    $etudiant1 = User::factory()->create(['role' => 'etudiant']);
    $etudiant2 = User::factory()->create(['role' => 'etudiant']);

    $classeSection = Classe::create(['cours_id' => $cours->id]);
    $classeSection->etudiants()->attach([$etudiant1->id, $etudiant2->id]);

    $groupe = Groupe::create([
        'classe_id' => $classeSection->id,
        'created_by' => $etudiant1->id,
    ]);
    $groupe->membres()->attach([$etudiant1->id, $etudiant2->id]);

    $projet = ProjetRecherche::create([
        'groupe_id' => $groupe->id,
        'type_projet_id' => $typeProjet->id,
    ]);

    $baseUrl = "/cours/{$cours->id}/classes/{$classeSection->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}";

    return compact('enseignant', 'typeProjet', 'cours', 'classeSection', 'groupe', 'etudiant1', 'etudiant2', 'projet', 'baseUrl');
}

// ─── Création ─────────────────────────────────────────────────────────────────

it('peut créer un renvoi avec numéro auto-incrémenté', function () {
    ['etudiant1' => $etudiant, 'baseUrl' => $url] = creerScenarioRenvoi();

    $response = $this->actingAs($etudiant)
        ->postJson("{$url}/renvois", ['contenu' => 'Source : Smith (2020).']);

    $response->assertCreated()
        ->assertJsonPath('renvoi.numero', 1)
        ->assertJsonPath('renvoi.contenu', 'Source : Smith (2020).');
});

it('incrémente correctement le numéro si des renvois existent déjà', function () {
    ['etudiant1' => $etudiant, 'projet' => $projet, 'baseUrl' => $url] = creerScenarioRenvoi();

    ProjetRenvoi::create(['projet_id' => $projet->id, 'numero' => 1, 'contenu' => 'Première source.']);

    $response = $this->actingAs($etudiant)
        ->postJson("{$url}/renvois", ['contenu' => 'Deuxième source.']);

    $response->assertCreated()
        ->assertJsonPath('renvoi.numero', 2);
});

// ─── Unicité ──────────────────────────────────────────────────────────────────

it('refuse un numéro dupliqué dans le même projet (contrainte DB)', function () {
    ['projet' => $projet] = creerScenarioRenvoi();

    ProjetRenvoi::create(['projet_id' => $projet->id, 'numero' => 1, 'contenu' => 'Premier.']);

    expect(fn () => ProjetRenvoi::create(['projet_id' => $projet->id, 'numero' => 1, 'contenu' => 'Doublon.']))
        ->toThrow(QueryException::class);
});

// ─── Mise à jour ──────────────────────────────────────────────────────────────

it('peut modifier le contenu d\'un renvoi', function () {
    ['etudiant1' => $etudiant, 'projet' => $projet, 'baseUrl' => $url] = creerScenarioRenvoi();

    $renvoi = ProjetRenvoi::create(['projet_id' => $projet->id, 'numero' => 1, 'contenu' => 'Ancien texte.']);

    $this->actingAs($etudiant)
        ->patchJson("{$url}/renvois/{$renvoi->id}", ['contenu' => 'Nouveau texte.'])
        ->assertOk()
        ->assertJsonPath('message', 'saved');

    expect($renvoi->fresh()->contenu)->toBe('Nouveau texte.');
});

it('peut modifier le numéro d\'un renvoi lors d\'une renumérotation', function () {
    ['etudiant1' => $etudiant, 'projet' => $projet, 'baseUrl' => $url] = creerScenarioRenvoi();

    // Simuler : refs 1, 2, 3 ; suppression de la 2 ; ref 3 doit devenir 2.
    ProjetRenvoi::create(['projet_id' => $projet->id, 'numero' => 1, 'contenu' => 'Source A.']);
    $renvoi3 = ProjetRenvoi::create(['projet_id' => $projet->id, 'numero' => 3, 'contenu' => 'Source C.']);

    $this->actingAs($etudiant)
        ->patchJson("{$url}/renvois/{$renvoi3->id}", ['numero' => 2])
        ->assertOk()
        ->assertJsonPath('message', 'saved');

    expect($renvoi3->fresh()->numero)->toBe(2);
});

// ─── Suppression ──────────────────────────────────────────────────────────────

it('peut supprimer un renvoi', function () {
    ['etudiant1' => $etudiant, 'projet' => $projet, 'baseUrl' => $url] = creerScenarioRenvoi();

    $renvoi = ProjetRenvoi::create(['projet_id' => $projet->id, 'numero' => 1, 'contenu' => 'À supprimer.']);

    $this->actingAs($etudiant)
        ->deleteJson("{$url}/renvois/{$renvoi->id}")
        ->assertOk()
        ->assertJsonPath('message', 'deleted');

    expect(ProjetRenvoi::find($renvoi->id))->toBeNull();
});

// ─── Autorisation ─────────────────────────────────────────────────────────────

it('un non-membre du groupe est bloqué lors de la création', function () {
    ['baseUrl' => $url] = creerScenarioRenvoi();

    $autre = User::factory()->create(['role' => 'etudiant']);

    $this->actingAs($autre)
        ->postJson("{$url}/renvois", ['contenu' => 'Tentative.'])
        ->assertForbidden();
});

it('un non-membre est bloqué lors de la mise à jour', function () {
    ['projet' => $projet, 'baseUrl' => $url] = creerScenarioRenvoi();

    $renvoi = ProjetRenvoi::create(['projet_id' => $projet->id, 'numero' => 1, 'contenu' => 'Texte.']);
    $autre = User::factory()->create(['role' => 'etudiant']);

    $this->actingAs($autre)
        ->patchJson("{$url}/renvois/{$renvoi->id}", ['contenu' => 'Piraté.'])
        ->assertForbidden();
});

// ─── Numérotation par projet ──────────────────────────────────────────────────

it('le numéro repart de 1 pour un nouveau projet', function () {
    ['etudiant1' => $etudiant, 'enseignant' => $enseignant, 'classeSection' => $cs, 'cours' => $cours, 'typeProjet' => $typeProjet] = creerScenarioRenvoi();

    // Deuxième groupe avec son propre projet
    $groupe2 = Groupe::create(['classe_id' => $cs->id, 'created_by' => $etudiant->id]);
    $groupe2->membres()->attach([$etudiant->id]);

    $projet2 = ProjetRecherche::create([
        'groupe_id' => $groupe2->id,
        'type_projet_id' => $typeProjet->id,
    ]);

    $url2 = "/cours/{$cours->id}/classes/{$cs->id}/groupes/{$groupe2->id}/projets/{$typeProjet->id}";

    $response = $this->actingAs($etudiant)
        ->postJson("{$url2}/renvois", ['contenu' => 'Première source du groupe 2.']);

    $response->assertCreated()
        ->assertJsonPath('renvoi.numero', 1);
});

it('le numéro est basé sur le maximum existant en base de données', function () {
    ['etudiant1' => $etudiant, 'projet' => $projet, 'baseUrl' => $url] = creerScenarioRenvoi();

    // Pré-insérer un renvoi avec un numéro non-séquentiel pour vérifier que
    // le calcul est bien max(numero) + 1 et non count() + 1.
    ProjetRenvoi::create(['projet_id' => $projet->id, 'numero' => 5, 'contenu' => 'Renvoi existant.']);

    $response = $this->actingAs($etudiant)
        ->postJson("{$url}/renvois", ['contenu' => 'Nouvelle source.']);

    $response->assertCreated()
        ->assertJsonPath('renvoi.numero', 6);
});

it('un projet sans renvoi retourne un tableau vide dans show()', function () {
    ['etudiant1' => $etudiant, 'cours' => $cours, 'classeSection' => $cs, 'groupe' => $groupe, 'typeProjet' => $typeProjet] = creerScenarioRenvoi();

    $this->actingAs($etudiant)
        ->get("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Projets/Show')
            ->where('renvois', [])
        );
});

// ─── Commentaires d'enseignant sur les renvois ────────────────────────────────

it('un enseignant peut ajouter un commentaire sur un renvoi', function () {
    ['enseignant' => $enseignant, 'projet' => $projet, 'baseUrl' => $url] = creerScenarioRenvoi();

    $renvoi = ProjetRenvoi::create(['projet_id' => $projet->id, 'numero' => 1, 'contenu' => 'Source test.']);

    $this->actingAs($enseignant)
        ->postJson("{$url}/renvois/{$renvoi->id}/commentaires", ['contenu' => 'Vérifier la source.'])
        ->assertCreated()
        ->assertJsonPath('message', 'created')
        ->assertJsonPath('commentaire.contenu', 'Vérifier la source.');

    expect(ProjetRenvoiCommentaire::where('renvoi_id', $renvoi->id)->count())->toBe(1);
});

it('un étudiant ne peut pas ajouter un commentaire sur un renvoi', function () {
    ['etudiant1' => $etudiant, 'projet' => $projet, 'baseUrl' => $url] = creerScenarioRenvoi();

    $renvoi = ProjetRenvoi::create(['projet_id' => $projet->id, 'numero' => 1, 'contenu' => 'Source test.']);

    $this->actingAs($etudiant)
        ->postJson("{$url}/renvois/{$renvoi->id}/commentaires", ['contenu' => 'Tentative.'])
        ->assertForbidden();
});

it('le contenu du commentaire est obligatoire', function () {
    ['enseignant' => $enseignant, 'projet' => $projet, 'baseUrl' => $url] = creerScenarioRenvoi();

    $renvoi = ProjetRenvoi::create(['projet_id' => $projet->id, 'numero' => 1, 'contenu' => 'Source test.']);

    $this->actingAs($enseignant)
        ->postJson("{$url}/renvois/{$renvoi->id}/commentaires", ['contenu' => ''])
        ->assertUnprocessable();
});

it('un enseignant peut supprimer son commentaire sur un renvoi', function () {
    ['enseignant' => $enseignant, 'projet' => $projet, 'baseUrl' => $url] = creerScenarioRenvoi();

    $renvoi = ProjetRenvoi::create(['projet_id' => $projet->id, 'numero' => 1, 'contenu' => 'Source test.']);
    $commentaire = ProjetRenvoiCommentaire::create([
        'renvoi_id' => $renvoi->id,
        'user_id' => $enseignant->id,
        'contenu' => 'Commentaire à supprimer.',
    ]);

    $this->actingAs($enseignant)
        ->deleteJson("{$url}/renvois/{$renvoi->id}/commentaires/{$commentaire->id}")
        ->assertOk()
        ->assertJsonPath('message', 'deleted');

    expect(ProjetRenvoiCommentaire::find($commentaire->id))->toBeNull();
});

it('impossible de supprimer un commentaire appartenant à un autre renvoi (anti-IDOR)', function () {
    ['enseignant' => $enseignant, 'projet' => $projet, 'baseUrl' => $url] = creerScenarioRenvoi();

    $renvoi1 = ProjetRenvoi::create(['projet_id' => $projet->id, 'numero' => 1, 'contenu' => 'A.']);
    $renvoi2 = ProjetRenvoi::create(['projet_id' => $projet->id, 'numero' => 2, 'contenu' => 'B.']);
    $commentaireDuRenvoi2 = ProjetRenvoiCommentaire::create([
        'renvoi_id' => $renvoi2->id,
        'user_id' => $enseignant->id,
        'contenu' => 'Commentaire du renvoi 2.',
    ]);

    // Tente de supprimer le commentaire de renvoi2 via l'URL de renvoi1 → 404
    $this->actingAs($enseignant)
        ->deleteJson("{$url}/renvois/{$renvoi1->id}/commentaires/{$commentaireDuRenvoi2->id}")
        ->assertNotFound();
});

it('les commentaires sont inclus dans show() pour l\'enseignant', function () {
    ['enseignant' => $enseignant, 'projet' => $projet, 'cours' => $cours, 'classeSection' => $cs, 'groupe' => $groupe, 'typeProjet' => $typeProjet] = creerScenarioRenvoi();

    $renvoi = ProjetRenvoi::create(['projet_id' => $projet->id, 'numero' => 1, 'contenu' => 'Ref.']);
    ProjetRenvoiCommentaire::create(['renvoi_id' => $renvoi->id, 'user_id' => $enseignant->id, 'contenu' => 'Mon commentaire.']);

    $this->actingAs($enseignant)
        ->get("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Projets/Show')
            ->has('renvois', 1)
            ->where('renvois.0.commentaires.0.contenu', 'Mon commentaire.')
        );
});

// ─── Mode édition enseignant ───────────────────────────────────────────────────

it('un enseignant peut activer le mode édition enseignant', function () {
    ['enseignant' => $enseignant, 'projet' => $projet, 'baseUrl' => $url] = creerScenarioRenvoi();

    expect($projet->mode_edition_enseignant)->toBeFalsy();

    $this->actingAs($enseignant)
        ->patchJson("{$url}/mode-edition-enseignant")
        ->assertOk()
        ->assertJsonPath('message', 'toggled')
        ->assertJsonPath('mode_edition_enseignant', true);

    expect($projet->fresh()->mode_edition_enseignant)->toBeTrue();
});

it('un enseignant peut désactiver le mode édition en le retogglant', function () {
    ['enseignant' => $enseignant, 'projet' => $projet, 'baseUrl' => $url] = creerScenarioRenvoi();

    $projet->update(['mode_edition_enseignant' => true]);

    $this->actingAs($enseignant)
        ->patchJson("{$url}/mode-edition-enseignant")
        ->assertOk()
        ->assertJsonPath('mode_edition_enseignant', false);

    expect($projet->fresh()->mode_edition_enseignant)->toBeFalse();
});

it('un étudiant ne peut pas toggler le mode édition enseignant', function () {
    ['etudiant1' => $etudiant, 'baseUrl' => $url] = creerScenarioRenvoi();

    $this->actingAs($etudiant)
        ->patchJson("{$url}/mode-edition-enseignant")
        ->assertForbidden();
});

it('show() retourne modeEditionEnseignant dans les props', function () {
    ['enseignant' => $enseignant, 'projet' => $projet, 'cours' => $cours, 'classeSection' => $cs, 'groupe' => $groupe, 'typeProjet' => $typeProjet] = creerScenarioRenvoi();

    $projet->update(['mode_edition_enseignant' => true]);

    $this->actingAs($enseignant)
        ->get("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Projets/Show')
            ->where('modeEditionEnseignant', true)
        );
});
