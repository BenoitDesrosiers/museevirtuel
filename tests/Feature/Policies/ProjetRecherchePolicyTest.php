<?php

use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Crée un cours avec son enseignant, une classe, un groupe d'un membre,
 * un témoin assigné et un projet de recherche.
 *
 * @return array{enseignant: User, membre: User, temoin: User, cours: Cours, classe: Classe, groupe: Groupe, typeProjet: TypeProjet, projet: ProjetRecherche}
 */
function creerScenarioPolicyProjet(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $membre = User::factory()->create(['role' => 'etudiant']);
    $temoin = User::factory()->create(['role' => 'personne_agee']);

    $cours = Cours::create([
        'nom_cours' => 'Histoire',
        'code' => '330-POL',
        'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);

    $classe = Classe::create(['cours_id' => $cours->id]);
    $classe->etudiants()->attach($membre->id);

    $groupe = Groupe::create([
        'classe_id' => $classe->id,
        'created_by' => $membre->id,
        'personne_agee_id' => $temoin->id,
    ]);
    $groupe->membres()->attach($membre->id);

    $typeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id,
        'cours_id' => $cours->id,
        'nom' => 'Projet',
        'accessible' => true,
        'remises_multiples' => false,
        'retard_permis' => true,
    ]);

    $projet = ProjetRecherche::create([
        'groupe_id' => $groupe->id,
        'type_projet_id' => $typeProjet->id,
    ]);

    return compact('enseignant', 'membre', 'temoin', 'cours', 'classe', 'groupe', 'typeProjet', 'projet');
}

/**
 * Crée l'enseignant d'un autre cours, sans lien avec le scénario.
 */
function enseignantAutreCours(): User
{
    $autre = User::factory()->create(['role' => 'enseignant']);
    Cours::create([
        'nom_cours' => 'Autre',
        'code' => '330-AUT',
        'groupe' => '02',
        'enseignant_id' => $autre->id,
    ]);

    return $autre;
}

// ─── Abilities au niveau du groupe ────────────────────────────────────────────

test('acceder : membre et enseignant du cours oui, témoin, intrus et enseignant d\'un autre cours non', function () {
    $s = creerScenarioPolicyProjet();
    $cible = [ProjetRecherche::class, $s['groupe']];

    expect($s['membre']->can('acceder', $cible))->toBeTrue()
        ->and($s['enseignant']->can('acceder', $cible))->toBeTrue()
        ->and($s['temoin']->can('acceder', $cible))->toBeFalse()
        ->and(User::factory()->create(['role' => 'etudiant'])->can('acceder', $cible))->toBeFalse()
        ->and(enseignantAutreCours()->can('acceder', $cible))->toBeFalse();
});

test('contribuer : réservé aux membres du groupe', function () {
    $s = creerScenarioPolicyProjet();
    $cible = [ProjetRecherche::class, $s['groupe']];

    expect($s['membre']->can('contribuer', $cible))->toBeTrue()
        ->and($s['enseignant']->can('contribuer', $cible))->toBeFalse()
        ->and($s['temoin']->can('contribuer', $cible))->toBeFalse();
});

test('consentir : membres et témoin assigné seulement', function () {
    $s = creerScenarioPolicyProjet();
    $cible = [ProjetRecherche::class, $s['groupe']];

    expect($s['membre']->can('consentir', $cible))->toBeTrue()
        ->and($s['temoin']->can('consentir', $cible))->toBeTrue()
        ->and($s['enseignant']->can('consentir', $cible))->toBeFalse()
        ->and(User::factory()->create(['role' => 'personne_agee'])->can('consentir', $cible))->toBeFalse();
});

test('corriger : réservé à l\'enseignant du cours', function () {
    $s = creerScenarioPolicyProjet();
    $cible = [ProjetRecherche::class, $s['groupe']];

    expect($s['enseignant']->can('corriger', $cible))->toBeTrue()
        ->and($s['membre']->can('corriger', $cible))->toBeFalse()
        ->and(enseignantAutreCours()->can('corriger', $cible))->toBeFalse();
});

// ─── editerContenu ────────────────────────────────────────────────────────────

test('editerContenu : un membre peut éditer un projet ouvert', function () {
    $s = creerScenarioPolicyProjet();

    expect($s['membre']->can('editerContenu', $s['projet']))->toBeTrue();
});

test('editerContenu : refusé (403) à un membre si le projet est verrouillé', function () {
    $s = creerScenarioPolicyProjet();
    $s['projet']->update(['verrouille' => true]);

    $reponse = Gate::forUser($s['membre'])->inspect('editerContenu', $s['projet']);

    expect($reponse->denied())->toBeTrue()
        ->and($reponse->status())->toBeNull()
        ->and($reponse->message())->toBe(__('projet.verrouille'));
});

test('editerContenu : refusé (422) à un membre si le projet est déjà remis', function () {
    $s = creerScenarioPolicyProjet();
    $s['projet']->update(['remis_le' => now()]);

    $reponse = Gate::forUser($s['membre'])->inspect('editerContenu', $s['projet']);

    expect($reponse->denied())->toBeTrue()
        ->and($reponse->status())->toBe(422);
});

test('editerContenu : la remise est lue sur le TypeProjet, pas sur les colonnes héritées du projet', function () {
    $s = creerScenarioPolicyProjet();
    $s['typeProjet']->update(['remises_multiples' => true]);
    // Colonne héritée contradictoire : elle ne doit pas être lue
    $s['projet']->update(['remis_le' => now(), 'remises_multiples' => false]);

    $projetSansRelation = ProjetRecherche::find($s['projet']->id);

    expect($s['membre']->can('editerContenu', $projetSansRelation))->toBeTrue();
});

test('editerContenu : l\'enseignant en mode édition passe outre le verrou et la remise', function () {
    $s = creerScenarioPolicyProjet();
    $s['projet']->update(['verrouille' => true, 'remis_le' => now()]);

    expect($s['enseignant']->can('editerContenu', $s['projet']))->toBeFalse();

    $s['projet']->update(['mode_edition_enseignant' => true]);

    expect($s['enseignant']->can('editerContenu', $s['projet']->fresh()))->toBeTrue()
        ->and(enseignantAutreCours()->can('editerContenu', $s['projet']->fresh()))->toBeFalse();
});

// ─── Intégration HTTP ─────────────────────────────────────────────────────────

test('un enseignant d\'un autre cours ne peut pas verrouiller le projet et aucun projet n\'est créé', function () {
    $s = creerScenarioPolicyProjet();
    $s['projet']->delete();

    $this->actingAs(enseignantAutreCours())
        ->patchJson("/cours/{$s['cours']->id}/classes/{$s['classe']->id}/groupes/{$s['groupe']->id}/projets/{$s['typeProjet']->id}/verrouille")
        ->assertForbidden();

    expect(ProjetRecherche::count())->toBe(0);
});

test('un groupe d\'une autre classe dans l\'URL renvoie 404', function () {
    $s = creerScenarioPolicyProjet();
    $autreClasse = Classe::create(['cours_id' => $s['cours']->id]);

    $this->actingAs($s['enseignant'])
        ->patchJson("/cours/{$s['cours']->id}/classes/{$autreClasse->id}/groupes/{$s['groupe']->id}/projets/{$s['typeProjet']->id}/verrouille")
        ->assertNotFound();
});
