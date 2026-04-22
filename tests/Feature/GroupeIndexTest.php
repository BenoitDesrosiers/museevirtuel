<?php

use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function creerScenarioGroupeIndex(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    $cours = Cours::create([
        'nom_cours' => 'Histoire du Québec',
        'description' => 'Test',
        'code' => '330-GRP',
        'groupe' => 'A',
        'enseignant_id' => $enseignant->id,
    ]);

    $etudiant = User::factory()->create(['role' => 'etudiant']);

    $classe = Classe::create(['cours_id' => $cours->id]);
    $classe->etudiants()->attach($etudiant->id);

    return compact('enseignant', 'cours', 'etudiant', 'classe');
}

// ─── index() — étudiant avec groupe ───────────────────────────────────────────

test('étudiant avec groupe est redirigé vers groupes show', function () {
    $ctx = creerScenarioGroupeIndex();

    $groupe = Groupe::create([
        'classe_id' => $ctx['classe']->id,
        'created_by' => $ctx['etudiant']->id,
    ]);
    $groupe->membres()->attach($ctx['etudiant']->id);

    $this->actingAs($ctx['etudiant'])
        ->get(route('groupes.index', [$ctx['cours'], $ctx['classe']]))
        ->assertRedirect(route('groupes.show', [$ctx['cours'], $ctx['classe'], $groupe]));
});

// ─── index() — étudiant sans groupe ───────────────────────────────────────────

test('étudiant sans groupe voit la page de création', function () {
    $ctx = creerScenarioGroupeIndex();

    $this->actingAs($ctx['etudiant'])
        ->get(route('groupes.index', [$ctx['cours'], $ctx['classe']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Classes/Groupes')
            ->has('autresEtudiants')
            ->has('thematiques')
            ->missing('documents')
            ->missing('echeancierEtapes')
            ->missing('monGroupe')
        );
});

// ─── index() — accès refusé ────────────────────────────────────────────────────

test('étudiant non inscrit ne peut pas accéder à groupes index', function () {
    $ctx = creerScenarioGroupeIndex();
    $autre = User::factory()->create(['role' => 'etudiant']);

    $this->actingAs($autre)
        ->get(route('groupes.index', [$ctx['cours'], $ctx['classe']]))
        ->assertForbidden();
});

test('cours et classe incompatibles renvoient 404', function () {
    $ctx = creerScenarioGroupeIndex();

    $autreCours = Cours::create([
        'nom_cours' => 'Autre',
        'description' => 'Autre',
        'code' => '330-XYZ',
        'groupe' => 'B',
        'enseignant_id' => $ctx['enseignant']->id,
    ]);

    $this->actingAs($ctx['etudiant'])
        ->get(route('groupes.index', [$autreCours, $ctx['classe']]))
        ->assertNotFound();
});

// ─── Lien sidebar étudiant ─────────────────────────────────────────────────────

test('la route etudiant.index redirige vers cours.index', function () {
    $etudiant = User::factory()->create(['role' => 'etudiant']);

    $this->actingAs($etudiant)
        ->get(route('etudiant.index'))
        ->assertRedirect(route('cours.index'));
});

// ─── index() — filtrage étudiants déjà dans un groupe ─────────────────────────

test('index exclut les étudiants déjà dans un groupe de autresEtudiants', function () {
    $ctx = creerScenarioGroupeIndex();

    // Un deuxième étudiant inscrit mais déjà dans un groupe
    $etudiantGroupe = User::factory()->create(['role' => 'etudiant']);
    $ctx['classe']->etudiants()->attach($etudiantGroupe->id);

    $groupeExistant = Groupe::create([
        'classe_id' => $ctx['classe']->id,
        'created_by' => $etudiantGroupe->id,
    ]);
    $groupeExistant->membres()->attach($etudiantGroupe->id);

    // Un troisième étudiant inscrit mais sans groupe — doit apparaître
    $etudiantLibre = User::factory()->create(['role' => 'etudiant']);
    $ctx['classe']->etudiants()->attach($etudiantLibre->id);

    $this->actingAs($ctx['etudiant'])
        ->get(route('groupes.index', [$ctx['cours'], $ctx['classe']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Classes/Groupes')
            ->where('autresEtudiants', fn ($list) => collect($list)->pluck('id')->doesntContain($etudiantGroupe->id)
                && collect($list)->pluck('id')->contains($etudiantLibre->id)
            )
        );
});

// ─── store() — filtrage membres déjà dans un groupe ──────────────────────────

test('store ignore les membres déjà dans un autre groupe', function () {
    $ctx = creerScenarioGroupeIndex();

    // Étudiant déjà dans un groupe existant
    $etudiantPris = User::factory()->create(['role' => 'etudiant']);
    $ctx['classe']->etudiants()->attach($etudiantPris->id);

    $groupeExistant = Groupe::create([
        'classe_id' => $ctx['classe']->id,
        'created_by' => $etudiantPris->id,
    ]);
    $groupeExistant->membres()->attach($etudiantPris->id);

    $this->actingAs($ctx['etudiant'])
        ->post(route('groupes.store', [$ctx['cours'], $ctx['classe']]), [
            'membres' => [$etudiantPris->id],
            'thematiques' => [],
        ]);

    // Le groupe créé ne doit contenir que le créateur
    $nouveauGroupe = Groupe::where('classe_id', $ctx['classe']->id)
        ->where('created_by', $ctx['etudiant']->id)
        ->first();

    expect($nouveauGroupe)->not->toBeNull()
        ->and($nouveauGroupe->membres()->pluck('users.id')->map(fn ($id) => (int) $id)->toArray())
        ->not->toContain((int) $etudiantPris->id);
});

// ─── show() — filtrage etudiantsDispo déjà dans un groupe ────────────────────

test('show exclut de etudiantsDispo les étudiants dans un autre groupe', function () {
    $ctx = creerScenarioGroupeIndex();

    // Groupe de l'étudiant courant
    $groupe = Groupe::create([
        'classe_id' => $ctx['classe']->id,
        'created_by' => $ctx['etudiant']->id,
    ]);
    $groupe->membres()->attach($ctx['etudiant']->id);

    // Étudiant dans un autre groupe
    $etudiantPris = User::factory()->create(['role' => 'etudiant']);
    $ctx['classe']->etudiants()->attach($etudiantPris->id);

    $autreGroupe = Groupe::create([
        'classe_id' => $ctx['classe']->id,
        'created_by' => $etudiantPris->id,
    ]);
    $autreGroupe->membres()->attach($etudiantPris->id);

    // Étudiant libre
    $etudiantLibre = User::factory()->create(['role' => 'etudiant']);
    $ctx['classe']->etudiants()->attach($etudiantLibre->id);

    $this->actingAs($ctx['etudiant'])
        ->get(route('groupes.show', [$ctx['cours'], $ctx['classe'], $groupe]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Groupes/Show')
            ->where('etudiantsDispo', fn ($list) => collect($list)->pluck('id')->doesntContain($etudiantPris->id)
                && collect($list)->pluck('id')->contains($etudiantLibre->id)
            )
        );
});

// ─── updateMembres() — filtrage étudiants déjà dans un groupe ────────────────

test('updateMembres ignore les étudiants déjà dans un autre groupe', function () {
    $ctx = creerScenarioGroupeIndex();

    // Groupe du créateur
    $groupe = Groupe::create([
        'classe_id' => $ctx['classe']->id,
        'created_by' => $ctx['etudiant']->id,
    ]);
    $groupe->membres()->attach($ctx['etudiant']->id);

    // Étudiant déjà dans un autre groupe
    $etudiantPris = User::factory()->create(['role' => 'etudiant']);
    $ctx['classe']->etudiants()->attach($etudiantPris->id);

    $autreGroupe = Groupe::create([
        'classe_id' => $ctx['classe']->id,
        'created_by' => $etudiantPris->id,
    ]);
    $autreGroupe->membres()->attach($etudiantPris->id);

    $this->actingAs($ctx['etudiant'])
        ->put(route('groupes.membres.update', [$ctx['cours'], $ctx['classe'], $groupe]), [
            'ajouter' => [$etudiantPris->id],
            'retirer' => [],
        ]);

    expect($groupe->membres()->pluck('users.id')->map(fn ($id) => (int) $id)->toArray())
        ->not->toContain((int) $etudiantPris->id);
});
