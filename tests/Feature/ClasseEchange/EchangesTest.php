<?php

use App\Models\Classe;
use App\Models\ClasseEchange;
use App\Models\Cours;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function creerScenarioEchanges(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    $cours = Cours::create([
        'nom_cours' => 'Histoire',
        'description' => 'Test',
        'code' => '330-ECH',
        'groupe' => 'A',
        'enseignant_id' => $enseignant->id,
    ]);

    $etudiant = User::factory()->create(['role' => 'etudiant']);
    $cours->etudiants()->attach($etudiant->id);

    $temoin = User::factory()->create([
        'role' => 'personne_agee',
        'statut' => 'actif',
        'email_verified_at' => now(),
    ]);

    $classe = Classe::create([
        'cours_id' => $cours->id,
        'created_by' => $etudiant->id,
        'personne_agee_id' => $temoin->id,
    ]);
    $classe->membres()->attach($etudiant->id);

    return compact('enseignant', 'cours', 'etudiant', 'temoin', 'classe');
}

// ─── index() ──────────────────────────────────────────────────────────────────

test('un membre de la classe peut voir les échanges', function () {
    $ctx = creerScenarioEchanges();

    $this->actingAs($ctx['etudiant'])
        ->get(route('echanges.index', [$ctx['cours'], $ctx['classe']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Classes/Echanges'));
});

test('le témoin assigné peut voir les échanges', function () {
    $ctx = creerScenarioEchanges();

    $this->actingAs($ctx['temoin'])
        ->get(route('echanges.index', [$ctx['cours'], $ctx['classe']]))
        ->assertOk();
});

test('l\'enseignant du cours peut voir les échanges', function () {
    $ctx = creerScenarioEchanges();

    $this->actingAs($ctx['enseignant'])
        ->get(route('echanges.index', [$ctx['cours'], $ctx['classe']]))
        ->assertOk();
});

test('une personne âgée non assignée à la classe est refusée', function () {
    $ctx = creerScenarioEchanges();
    $autreTemoin = User::factory()->create([
        'role' => 'personne_agee',
        'statut' => 'actif',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($autreTemoin)
        ->get(route('echanges.index', [$ctx['cours'], $ctx['classe']]))
        ->assertForbidden();
});

test('un invité est redirigé vers le login', function () {
    $ctx = creerScenarioEchanges();

    $this->get(route('echanges.index', [$ctx['cours'], $ctx['classe']]))
        ->assertRedirect(route('login'));
});

// ─── store() ──────────────────────────────────────────────────────────────────

test('un membre peut envoyer un message', function () {
    $ctx = creerScenarioEchanges();

    $this->actingAs($ctx['etudiant'])
        ->post(route('echanges.store', [$ctx['cours'], $ctx['classe']]), [
            'contenu' => 'Bonjour, comment allez-vous ?',
        ])
        ->assertRedirect();

    expect(ClasseEchange::where('classe_id', $ctx['classe']->id)->count())->toBe(1);
    expect(ClasseEchange::first()->auteur_id)->toBe($ctx['etudiant']->id);
});

test('le témoin assigné peut répondre', function () {
    $ctx = creerScenarioEchanges();

    $this->actingAs($ctx['temoin'])
        ->post(route('echanges.store', [$ctx['cours'], $ctx['classe']]), [
            'contenu' => 'Très bien, merci !',
        ])
        ->assertRedirect();

    expect(ClasseEchange::first()->auteur_id)->toBe($ctx['temoin']->id);
});

test('l\'enseignant peut envoyer un message', function () {
    $ctx = creerScenarioEchanges();

    $this->actingAs($ctx['enseignant'])
        ->post(route('echanges.store', [$ctx['cours'], $ctx['classe']]), [
            'contenu' => 'Message de l\'enseignant.',
        ])
        ->assertRedirect();

    expect(ClasseEchange::count())->toBe(1);
});

test('un message vide est rejeté', function () {
    $ctx = creerScenarioEchanges();

    $this->actingAs($ctx['etudiant'])
        ->post(route('echanges.store', [$ctx['cours'], $ctx['classe']]), [
            'contenu' => '',
        ])
        ->assertSessionHasErrors('contenu');
});

test('un message de plus de 3000 caractères est rejeté', function () {
    $ctx = creerScenarioEchanges();

    $this->actingAs($ctx['etudiant'])
        ->post(route('echanges.store', [$ctx['cours'], $ctx['classe']]), [
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
        ->post(route('echanges.store', [$ctx['cours'], $ctx['classe']]), [
            'contenu' => 'Tentative intrusion.',
        ])
        ->assertForbidden();
});

// ─── PersonneAgee dashboard ───────────────────────────────────────────────────

test('la page personne âgée liste les classes assignées', function () {
    $ctx = creerScenarioEchanges();

    $this->actingAs($ctx['temoin'])
        ->get(route('personne-agee.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('classes', 1)
            ->where('classes.0.id', $ctx['classe']->id)
        );
});

test('la page personne âgée n\'affiche pas les classes d\'une autre personne', function () {
    $ctx = creerScenarioEchanges();
    $autreTemoin = User::factory()->create([
        'role' => 'personne_agee',
        'statut' => 'actif',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($autreTemoin)
        ->get(route('personne-agee.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('classes', 0));
});
