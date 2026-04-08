<?php

use App\Models\User;

// ─── Approbation ──────────────────────────────────────────────────────────────

test('un admin peut approuver un témoin en attente', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $pa = User::factory()->create([
        'role' => 'personne_agee',
        'statut' => 'en_attente',
        'email_verified_at' => null,
    ]);

    $this->actingAs($admin)
        ->put(route('administration.temoins.approuver', $pa))
        ->assertRedirect();

    $pa->refresh();

    expect($pa->statut)->toBe('actif')
        ->and($pa->email_verified_at)->not->toBeNull();
});

test('un non-admin est redirigé s\'il tente d\'approuver un témoin', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $pa = User::factory()->create([
        'role' => 'personne_agee',
        'statut' => 'en_attente',
    ]);

    // EnsureRole redirige vers /dashboard plutôt que de retourner 403
    $this->actingAs($enseignant)
        ->put(route('administration.temoins.approuver', $pa))
        ->assertRedirect(route('dashboard'));
});

test('on ne peut pas approuver un utilisateur qui n\'est pas personne_agee', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $etudiant = User::factory()->create(['role' => 'etudiant', 'statut' => 'actif']);

    $this->actingAs($admin)
        ->put(route('administration.temoins.approuver', $etudiant))
        ->assertForbidden();
});

test('on ne peut pas approuver un témoin déjà actif', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $pa = User::factory()->create([
        'role' => 'personne_agee',
        'statut' => 'actif',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($admin)
        ->put(route('administration.temoins.approuver', $pa))
        ->assertForbidden();
});

test('un invité ne peut pas approuver un témoin', function () {
    $pa = User::factory()->create([
        'role' => 'personne_agee',
        'statut' => 'en_attente',
    ]);

    $this->put(route('administration.temoins.approuver', $pa))
        ->assertRedirect(route('login'));
});

// ─── Panneau admin ────────────────────────────────────────────────────────────

test('le panneau admin affiche les témoins en attente', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $pa = User::factory()->create([
        'role' => 'personne_agee',
        'statut' => 'en_attente',
        'description' => 'Je viens de Québec.',
    ]);

    $response = $this->actingAs($admin)
        ->get(route('administration.index'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('temoinsEnAttente', 1)
            ->where('temoinsEnAttente.0.id', $pa->id)
        );
});

test('les témoins actifs n\'apparaissent pas dans la liste en attente', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    User::factory()->create([
        'role' => 'personne_agee',
        'statut' => 'actif',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('administration.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('temoinsEnAttente', 0)
        );
});

// ─── Page d'accueil PA ────────────────────────────────────────────────────────

test('une personne âgée approuvée accède à sa page d\'accueil', function () {
    $pa = User::factory()->create([
        'role' => 'personne_agee',
        'statut' => 'actif',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($pa)
        ->get(route('personne-agee.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('PersonneAgee/Index'));
});

test('un étudiant ne peut pas accéder à la page personne âgée', function () {
    $etudiant = User::factory()->create(['role' => 'etudiant']);

    $this->actingAs($etudiant)
        ->get(route('personne-agee.index'))
        ->assertRedirect(route('dashboard'));
});

test('le dashboard redirige une PA approuvée vers sa page', function () {
    $pa = User::factory()->create([
        'role' => 'personne_agee',
        'statut' => 'actif',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($pa)
        ->get(route('dashboard'))
        ->assertRedirect(route('personne-agee.index'));
});
