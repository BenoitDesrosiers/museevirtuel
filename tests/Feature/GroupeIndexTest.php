<?php

use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\Thematique;
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

// ─── create() — étudiant avec groupe ──────────────────────────────────────────

test('étudiant avec groupe est redirigé vers groupes show depuis create', function () {
    $ctx = creerScenarioGroupeIndex();

    $groupe = Groupe::create([
        'classe_id' => $ctx['classe']->id,
        'created_by' => $ctx['etudiant']->id,
    ]);
    $groupe->membres()->attach($ctx['etudiant']->id);

    $this->actingAs($ctx['etudiant'])
        ->get(route('groupes.create', [$ctx['cours'], $ctx['classe']]))
        ->assertRedirect(route('groupes.show', [$ctx['cours'], $ctx['classe'], $groupe]));
});

// ─── create() — étudiant sans groupe ──────────────────────────────────────────

test('étudiant sans groupe voit la nouvelle page de création', function () {
    $ctx = creerScenarioGroupeIndex();

    $this->actingAs($ctx['etudiant'])
        ->get(route('groupes.create', [$ctx['cours'], $ctx['classe']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Groupes/Create')
            ->has('autresEtudiants')
            ->has('thematiques')
            ->missing('documents')
            ->missing('echeancierEtapes')
            ->missing('monGroupe')
        );
});

test('index redirige vers la page de création', function () {
    $ctx = creerScenarioGroupeIndex();

    $this->actingAs($ctx['etudiant'])
        ->get(route('groupes.index', [$ctx['cours'], $ctx['classe']]))
        ->assertRedirect(route('groupes.create', [$ctx['cours'], $ctx['classe']]));
});

test('un enseignant ne peut pas accéder aux routes de création de groupe', function () {
    $ctx = creerScenarioGroupeIndex();

    $this->actingAs($ctx['enseignant'])
        ->get(route('groupes.index', [$ctx['cours'], $ctx['classe']]))
        ->assertRedirect(route('dashboard'));

    $this->actingAs($ctx['enseignant'])
        ->get(route('groupes.create', [$ctx['cours'], $ctx['classe']]))
        ->assertRedirect(route('dashboard'));

    $this->actingAs($ctx['enseignant'])
        ->post(route('groupes.store', [$ctx['cours'], $ctx['classe']]))
        ->assertRedirect(route('dashboard'));
});

// ─── index() — accès refusé ────────────────────────────────────────────────────

test('étudiant non inscrit ne peut pas accéder à groupes index', function () {
    $ctx = creerScenarioGroupeIndex();
    $autre = User::factory()->create(['role' => 'etudiant']);

    $this->actingAs($autre)
        ->get(route('groupes.create', [$ctx['cours'], $ctx['classe']]))
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
        ->get(route('groupes.create', [$autreCours, $ctx['classe']]))
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
        ->get(route('groupes.create', [$ctx['cours'], $ctx['classe']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Groupes/Create')
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
            'thematiques' => [Thematique::factory()->create([
                'enseignant_id' => $ctx['enseignant']->id,
            ])->id],
        ]);

    expect(Groupe::where('classe_id', $ctx['classe']->id)
        ->where('created_by', $ctx['etudiant']->id)
        ->exists())->toBeFalse();
});

test('store refuse un groupe avec moins de deux membres', function () {
    $ctx = creerScenarioGroupeIndex();
    $thematique = Thematique::factory()->create([
        'enseignant_id' => $ctx['enseignant']->id,
    ]);

    $this->actingAs($ctx['etudiant'])
        ->post(route('groupes.store', [$ctx['cours'], $ctx['classe']]), [
            'membres' => [],
            'thematiques' => [$thematique->id],
        ])
        ->assertSessionHasErrors('membres');
});

test('store refuse un groupe sans thematique', function () {
    $ctx = creerScenarioGroupeIndex();
    $etudiant = User::factory()->create(['role' => 'etudiant']);
    $ctx['classe']->etudiants()->attach($etudiant->id);

    $this->actingAs($ctx['etudiant'])
        ->post(route('groupes.store', [$ctx['cours'], $ctx['classe']]), [
            'membres' => [$etudiant->id],
            'thematiques' => [],
        ])
        ->assertSessionHasErrors('thematiques');
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

test('updateMembres refuse de réduire un groupe à un seul membre', function () {
    $ctx = creerScenarioGroupeIndex();
    $secondEtudiant = User::factory()->create(['role' => 'etudiant']);
    $ctx['classe']->etudiants()->attach($secondEtudiant->id);

    $groupe = Groupe::create([
        'classe_id' => $ctx['classe']->id,
        'created_by' => $ctx['etudiant']->id,
    ]);
    $groupe->membres()->attach([$ctx['etudiant']->id, $secondEtudiant->id]);

    $this->actingAs($ctx['etudiant'])
        ->put(route('groupes.membres.update', [$ctx['cours'], $ctx['classe'], $groupe]), [
            'ajouter' => [],
            'retirer' => [$secondEtudiant->id],
        ])
        ->assertSessionHasErrors('membres');
});

test('updateThematiques refuse de supprimer la dernière thematique', function () {
    $ctx = creerScenarioGroupeIndex();
    $secondEtudiant = User::factory()->create(['role' => 'etudiant']);
    $ctx['classe']->etudiants()->attach($secondEtudiant->id);
    $thematique = Thematique::factory()->create([
        'enseignant_id' => $ctx['enseignant']->id,
    ]);

    $groupe = Groupe::create([
        'classe_id' => $ctx['classe']->id,
        'created_by' => $ctx['etudiant']->id,
    ]);
    $groupe->membres()->attach([$ctx['etudiant']->id, $secondEtudiant->id]);
    $groupe->thematiques()->attach($thematique->id);

    $this->actingAs($ctx['etudiant'])
        ->put(route('groupes.thematiques.update', [$ctx['cours'], $ctx['classe'], $groupe]), [
            'thematiques' => [],
        ])
        ->assertSessionHasErrors('thematiques');
});
