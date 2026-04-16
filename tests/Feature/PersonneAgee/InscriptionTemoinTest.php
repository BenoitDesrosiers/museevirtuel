<?php

use App\Models\Thematique;
use App\Models\User;

// ─── Formulaire ───────────────────────────────────────────────────────────────

test('la page d\'inscription témoin est accessible sans authentification', function () {
    $this->get(route('inscription.temoin'))
        ->assertOk();
});

test('un utilisateur connecté peut quand même accéder à la page d\'inscription', function () {
    $user = User::factory()->create(['role' => 'etudiant']);

    $this->actingAs($user)
        ->get(route('inscription.temoin'))
        ->assertOk();
});

// ─── Soumission valide ────────────────────────────────────────────────────────

test('une inscription valide avec theme_libre crée un utilisateur en_attente', function () {
    $this->post(route('inscription.temoin.store'), [
        'prenom' => 'Marguerite',
        'nom' => 'Beauchamp',
        'email' => 'marguerite@exemple.com',
        'password' => 'Motdepasse1!',
        'password_confirmation' => 'Motdepasse1!',
        'theme_libre' => 'Les métiers d\'autrefois',
        'description' => 'Je suis née à Québec en 1945 et je souhaite partager mon vécu.',
    ])->assertRedirect(route('inscription.temoin'));

    $user = User::where('email', 'marguerite@exemple.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->role)->toBe('personne_agee')
        ->and($user->statut)->toBe('en_attente')
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->theme_libre)->toBe('Les métiers d\'autrefois');
});

test('une inscription valide avec une thématique existante crée un utilisateur en_attente', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $thematique = Thematique::factory()->create(['enseignant_id' => $enseignant->id]);

    $this->post(route('inscription.temoin.store'), [
        'prenom' => 'Marcel',
        'nom' => 'Tremblay',
        'email' => 'marcel@exemple.com',
        'password' => 'Motdepasse1!',
        'password_confirmation' => 'Motdepasse1!',
        'thematique_id' => $thematique->id,
        'description' => 'Je viens de la Mauricie et j\'ai beaucoup à raconter.',
    ])->assertRedirect(route('inscription.temoin'));

    $user = User::where('email', 'marcel@exemple.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->thematique_id)->toBe($thematique->id)
        ->and($user->statut)->toBe('en_attente');
});

// ─── Validation ───────────────────────────────────────────────────────────────

test('un email déjà utilisé est rejeté', function () {
    User::factory()->create(['email' => 'existant@exemple.com']);

    $this->post(route('inscription.temoin.store'), [
        'prenom' => 'Jean',
        'nom' => 'Dupont',
        'email' => 'existant@exemple.com',
        'password' => 'Motdepasse1!',
        'password_confirmation' => 'Motdepasse1!',
        'theme_libre' => 'Mon thème',
        'description' => 'Ma description.',
    ])->assertSessionHasErrors('email');
});

test('les champs obligatoires sont validés', function (string $field) {
    $data = [
        'prenom' => 'Jean',
        'nom' => 'Dupont',
        'email' => 'jean@exemple.com',
        'password' => 'Motdepasse1!',
        'password_confirmation' => 'Motdepasse1!',
        'theme_libre' => 'Mon thème',
        'description' => 'Ma description.',
    ];

    unset($data[$field]);

    $this->post(route('inscription.temoin.store'), $data)
        ->assertSessionHasErrors($field);
})->with(['prenom', 'nom', 'email', 'password', 'description']);

test('ni thematique_id ni theme_libre retourne une erreur', function () {
    $this->post(route('inscription.temoin.store'), [
        'prenom' => 'Lise',
        'nom' => 'Gagnon',
        'email' => 'lise@exemple.com',
        'password' => 'Motdepasse1!',
        'password_confirmation' => 'Motdepasse1!',
        'description' => 'Je suis de Montréal.',
    ])->assertSessionHasErrors('theme_libre');
});

test('une thematique_id inexistante est rejetée', function () {
    $this->post(route('inscription.temoin.store'), [
        'prenom' => 'Lise',
        'nom' => 'Gagnon',
        'email' => 'lise@exemple.com',
        'password' => 'Motdepasse1!',
        'password_confirmation' => 'Motdepasse1!',
        'thematique_id' => 9999,
        'description' => 'Je suis de Montréal.',
    ])->assertSessionHasErrors('thematique_id');
});

test('les mots de passe non concordants sont rejetés', function () {
    $this->post(route('inscription.temoin.store'), [
        'prenom' => 'Jean',
        'nom' => 'Dupont',
        'email' => 'jean@exemple.com',
        'password' => 'Motdepasse1!',
        'password_confirmation' => 'DifferentMotDePasse1!',
        'theme_libre' => 'Mon thème',
        'description' => 'Ma description.',
    ])->assertSessionHasErrors('password');
});

// ─── Connexion bloquée ────────────────────────────────────────────────────────

test('une personne âgée en_attente ne peut pas se connecter', function () {
    $pa = User::factory()->create([
        'role' => 'personne_agee',
        'statut' => 'en_attente',
        'email_verified_at' => null,
    ]);

    $this->post(route('login.store'), [
        'email' => $pa->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
});

test('une personne âgée approuvée peut se connecter et est redirigée vers sa page', function () {
    $pa = User::factory()->create([
        'role' => 'personne_agee',
        'statut' => 'actif',
        'email_verified_at' => now(),
    ]);

    $this->post(route('login.store'), [
        'email' => $pa->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();

    $this->get(route('dashboard'))
        ->assertRedirect(route('temoin.index'));
});
