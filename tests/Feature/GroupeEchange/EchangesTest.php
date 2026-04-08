<?php

use App\Models\Classe;
use App\Models\Groupe;
use App\Models\GroupeEchange;
use App\Models\User;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function creerScenarioEchanges(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    $classe = Classe::create([
        'nom_cours' => 'Histoire',
        'description' => 'Test',
        'code' => '330-ECH',
        'groupe' => 'A',
        'enseignant_id' => $enseignant->id,
    ]);

    $etudiant = User::factory()->create(['role' => 'etudiant']);
    $classe->etudiants()->attach($etudiant->id);

    $temoin = User::factory()->create([
        'role' => 'personne_agee',
        'statut' => 'actif',
        'email_verified_at' => now(),
    ]);

    $groupe = Groupe::create([
        'classe_id' => $classe->id,
        'created_by' => $etudiant->id,
        'personne_agee_id' => $temoin->id,
    ]);
    $groupe->membres()->attach($etudiant->id);

    return compact('enseignant', 'classe', 'etudiant', 'temoin', 'groupe');
}

// ─── index() ──────────────────────────────────────────────────────────────────

test('un membre du groupe peut voir les échanges', function () {
    $ctx = creerScenarioEchanges();

    $this->actingAs($ctx['etudiant'])
        ->get(route('echanges.index', [$ctx['classe'], $ctx['groupe']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Groupes/Echanges'));
});

test('le témoin assigné peut voir les échanges', function () {
    $ctx = creerScenarioEchanges();

    $this->actingAs($ctx['temoin'])
        ->get(route('echanges.index', [$ctx['classe'], $ctx['groupe']]))
        ->assertOk();
});

test('l\'enseignant de la classe peut voir les échanges', function () {
    $ctx = creerScenarioEchanges();

    $this->actingAs($ctx['enseignant'])
        ->get(route('echanges.index', [$ctx['classe'], $ctx['groupe']]))
        ->assertOk();
});

test('une personne âgée non assignée au groupe est refusée', function () {
    $ctx = creerScenarioEchanges();
    $autreTemoin = User::factory()->create([
        'role' => 'personne_agee',
        'statut' => 'actif',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($autreTemoin)
        ->get(route('echanges.index', [$ctx['classe'], $ctx['groupe']]))
        ->assertForbidden();
});

test('un invité est redirigé vers le login', function () {
    $ctx = creerScenarioEchanges();

    $this->get(route('echanges.index', [$ctx['classe'], $ctx['groupe']]))
        ->assertRedirect(route('login'));
});

// ─── store() ──────────────────────────────────────────────────────────────────

test('un membre peut envoyer un message', function () {
    $ctx = creerScenarioEchanges();

    $this->actingAs($ctx['etudiant'])
        ->post(route('echanges.store', [$ctx['classe'], $ctx['groupe']]), [
            'contenu' => 'Bonjour, comment allez-vous ?',
        ])
        ->assertRedirect();

    expect(GroupeEchange::where('groupe_id', $ctx['groupe']->id)->count())->toBe(1);
    expect(GroupeEchange::first()->auteur_id)->toBe($ctx['etudiant']->id);
});

test('le témoin assigné peut répondre', function () {
    $ctx = creerScenarioEchanges();

    $this->actingAs($ctx['temoin'])
        ->post(route('echanges.store', [$ctx['classe'], $ctx['groupe']]), [
            'contenu' => 'Très bien, merci !',
        ])
        ->assertRedirect();

    expect(GroupeEchange::first()->auteur_id)->toBe($ctx['temoin']->id);
});

test('l\'enseignant peut envoyer un message', function () {
    $ctx = creerScenarioEchanges();

    $this->actingAs($ctx['enseignant'])
        ->post(route('echanges.store', [$ctx['classe'], $ctx['groupe']]), [
            'contenu' => 'Message de l\'enseignant.',
        ])
        ->assertRedirect();

    expect(GroupeEchange::count())->toBe(1);
});

test('un message vide est rejeté', function () {
    $ctx = creerScenarioEchanges();

    $this->actingAs($ctx['etudiant'])
        ->post(route('echanges.store', [$ctx['classe'], $ctx['groupe']]), [
            'contenu' => '',
        ])
        ->assertSessionHasErrors('contenu');
});

test('un message de plus de 3000 caractères est rejeté', function () {
    $ctx = creerScenarioEchanges();

    $this->actingAs($ctx['etudiant'])
        ->post(route('echanges.store', [$ctx['classe'], $ctx['groupe']]), [
            'contenu' => str_repeat('a', 3001),
        ])
        ->assertSessionHasErrors('contenu');
});

test('une personne âgée non assignée ne peut pas envoyer de message', function () {
    $ctx = creerScenarioEchanges();
    $autreTemoin = User::factory()->create([
        'role' => 'personne_agee',
        'statut' => 'actif',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($autreTemoin)
        ->post(route('echanges.store', [$ctx['classe'], $ctx['groupe']]), [
            'contenu' => 'Tentative intrusion.',
        ])
        ->assertForbidden();
});

// ─── PersonneAgee dashboard ───────────────────────────────────────────────────

test('la page personne âgée liste les groupes assignés', function () {
    $ctx = creerScenarioEchanges();

    $this->actingAs($ctx['temoin'])
        ->get(route('personne-agee.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('groupes', 1)
            ->where('groupes.0.id', $ctx['groupe']->id)
        );
});

test('la page personne âgée n\'affiche pas les groupes d\'une autre personne', function () {
    $ctx = creerScenarioEchanges();
    $autreTemoin = User::factory()->create([
        'role' => 'personne_agee',
        'statut' => 'actif',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($autreTemoin)
        ->get(route('personne-agee.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('groupes', 0));
});
