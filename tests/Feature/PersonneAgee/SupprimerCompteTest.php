<?php

use App\Models\User;

// ─── Suppression de compte ─────────────────────────────────────────────────────

test('une personne âgée peut supprimer son propre compte', function () {
    $pa = User::factory()->create([
        'role' => 'personne_agee',
        'statut' => 'actif',
        'password' => bcrypt('password'),
        'email_verified_at' => now(),
    ]);

    $this->actingAs($pa)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertRedirect('/');

    $this->assertDatabaseMissing('users', ['id' => $pa->id]);
});

test('une PA est déconnectée après avoir supprimé son compte', function () {
    $pa = User::factory()->create([
        'role' => 'personne_agee',
        'statut' => 'actif',
        'password' => bcrypt('password'),
        'email_verified_at' => now(),
    ]);

    $this->actingAs($pa)
        ->delete(route('profile.destroy'), ['password' => 'password']);

    $this->assertGuest();
});

test('une PA ne peut pas supprimer son compte avec un mauvais mot de passe', function () {
    $pa = User::factory()->create([
        'role' => 'personne_agee',
        'statut' => 'actif',
        'password' => bcrypt('password'),
        'email_verified_at' => now(),
    ]);

    $this->actingAs($pa)
        ->delete(route('profile.destroy'), ['password' => 'mauvais_mot_de_passe'])
        ->assertSessionHasErrors();

    $this->assertDatabaseHas('users', ['id' => $pa->id]);
});

test('un étudiant ne peut pas supprimer son propre compte', function () {
    $etudiant = User::factory()->create([
        'role' => 'etudiant',
        'password' => bcrypt('password'),
        'email_verified_at' => now(),
    ]);

    $this->actingAs($etudiant)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertForbidden();

    $this->assertDatabaseHas('users', ['id' => $etudiant->id]);
});

test('un enseignant ne peut pas supprimer son propre compte', function () {
    $enseignant = User::factory()->create([
        'role' => 'enseignant',
        'password' => bcrypt('password'),
        'email_verified_at' => now(),
    ]);

    $this->actingAs($enseignant)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertForbidden();

    $this->assertDatabaseHas('users', ['id' => $enseignant->id]);
});

test('un invité ne peut pas accéder à la suppression de compte', function () {
    $this->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertRedirect(route('login'));
});
