<?php

use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetRecherche;
use App\Models\ProjetRenvoi;
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
