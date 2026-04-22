<?php

use App\Http\Controllers\InscriptionTemoinController;
use App\Models\Etablissement;
use App\Models\Thematique;
use App\Models\User;

// ─── Étape 1 : formulaire ─────────────────────────────────────────────────────

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

test('une inscription valide redirige vers la page des engagements', function () {
    $cegep = Etablissement::create(['nom' => 'Cégep Test', 'ville' => 'Québec']);

    $this->post(route('inscription.temoin.store'), [
        'prenom' => 'Marguerite',
        'nom' => 'Beauchamp',
        'email' => 'marguerite@exemple.com',
        'password' => 'Motdepasse1!',
        'password_confirmation' => 'Motdepasse1!',
        'choix' => [['etablissement_id' => $cegep->id, 'theme_libre' => 'Les métiers d\'autrefois']],
        'description' => 'Je suis née à Québec en 1945.',
    ])->assertRedirect(route('inscription.temoin.engagements'));

    // Aucun compte créé avant l'étape 2
    expect(User::where('email', 'marguerite@exemple.com')->exists())->toBeFalse();
});

test('les données de l\'étape 1 sont stockées en session lors d\'une soumission valide', function () {
    $cegep = Etablissement::create(['nom' => 'Cégep Test', 'ville' => 'Laval']);

    $this->post(route('inscription.temoin.store'), [
        'prenom' => 'Louise',
        'nom' => 'Roy',
        'email' => 'louise@exemple.com',
        'password' => 'Motdepasse1!',
        'password_confirmation' => 'Motdepasse1!',
        'choix' => [['etablissement_id' => $cegep->id, 'theme_libre' => 'Mon vécu']],
        'description' => 'Ma description.',
    ])->assertSessionHas('inscription_temoin_step1');
});

// ─── Étape 1 : validation ─────────────────────────────────────────────────────

test('un email déjà utilisé est rejeté à l\'étape 1', function () {
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

test('les champs obligatoires sont validés à l\'étape 1', function (string $field) {
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

test('ni thematique_id ni theme_libre retourne une erreur à l\'étape 1', function () {
    $cegep = Etablissement::create(['nom' => 'Cégep Test', 'ville' => 'Montréal']);

    $this->post(route('inscription.temoin.store'), [
        'prenom' => 'Lise',
        'nom' => 'Gagnon',
        'email' => 'lise@exemple.com',
        'password' => 'Motdepasse1!',
        'password_confirmation' => 'Motdepasse1!',
        'choix' => [['etablissement_id' => $cegep->id]],
        'description' => 'Je suis de Montréal.',
    ])->assertSessionHasErrors('choix.0.theme_libre');
});

test('une thematique_id inexistante est rejetée à l\'étape 1', function () {
    $cegep = Etablissement::create(['nom' => 'Cégep Test', 'ville' => 'Montréal']);

    $this->post(route('inscription.temoin.store'), [
        'prenom' => 'Lise',
        'nom' => 'Gagnon',
        'email' => 'lise@exemple.com',
        'password' => 'Motdepasse1!',
        'password_confirmation' => 'Motdepasse1!',
        'choix' => [['etablissement_id' => $cegep->id, 'thematique_ids' => [9999]]],
        'description' => 'Je suis de Montréal.',
    ])->assertSessionHasErrors('choix.0.thematique_ids.0');
});

test('les mots de passe non concordants sont rejetés à l\'étape 1', function () {
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

// ─── Étape 2 : page des engagements ──────────────────────────────────────────

test('la page des engagements est accessible si la session est présente', function () {
    $step1 = ['prenom' => 'Marguerite', 'nom' => 'Beauchamp', 'email' => 'x@x.com', 'password' => 'pwd', 'choix' => [], 'description' => 'x'];

    $this->withSession(['inscription_temoin_step1' => $step1])
        ->get(route('inscription.temoin.engagements'))
        ->assertOk();
});

test('la page des engagements redirige vers l\'étape 1 si la session est absente', function () {
    $this->get(route('inscription.temoin.engagements'))
        ->assertRedirect(route('inscription.temoin'));
});

// ─── Étape 2 : soumission valide ─────────────────────────────────────────────

test('une inscription complète en deux étapes crée un utilisateur en_attente avec signature', function () {
    $cegep = Etablissement::create(['nom' => 'Cégep Complet', 'ville' => 'Québec']);

    $step1 = [
        'prenom' => 'Marguerite',
        'nom' => 'Beauchamp',
        'email' => 'marguerite@exemple.com',
        'password' => 'Motdepasse1!',
        'choix' => [['etablissement_id' => $cegep->id, 'theme_libre' => 'Les métiers d\'autrefois', 'thematique_ids' => []]],
        'description' => 'Je suis née à Québec en 1945.',
    ];

    $this->withSession(['inscription_temoin_step1' => $step1])
        ->post(route('inscription.temoin.engagements.store'), [
            'engagements' => array_fill(0, InscriptionTemoinController::NB_ENGAGEMENTS, true),
            'signature' => 'Marguerite Beauchamp',
        ])
        ->assertRedirect(route('inscription.temoin'));

    $user = User::where('email', 'marguerite@exemple.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->role)->toBe('personne_agee')
        ->and($user->statut)->toBe('en_attente')
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->engagements_acceptes_le)->not->toBeNull()
        ->and($user->signature_electronique)->toBe('Marguerite Beauchamp');
});

test('une inscription avec thématique crée les pivots corrects', function () {
    $cegep = Etablissement::create(['nom' => 'Cégep Thématique', 'ville' => 'Montréal']);
    $enseignant = User::factory()->create(['role' => 'enseignant', 'etablissement_id' => $cegep->id]);
    $thematique = Thematique::factory()->create(['enseignant_id' => $enseignant->id, 'etablissement_id' => $cegep->id]);

    $step1 = [
        'prenom' => 'Marcel',
        'nom' => 'Tremblay',
        'email' => 'marcel@exemple.com',
        'password' => 'Motdepasse1!',
        'choix' => [['etablissement_id' => $cegep->id, 'thematique_ids' => [$thematique->id], 'theme_libre' => '']],
        'description' => 'Je viens de la Mauricie.',
    ];

    $this->withSession(['inscription_temoin_step1' => $step1])
        ->post(route('inscription.temoin.engagements.store'), [
            'engagements' => array_fill(0, InscriptionTemoinController::NB_ENGAGEMENTS, true),
            'signature' => 'Marcel Tremblay',
        ])
        ->assertRedirect(route('inscription.temoin'));

    $user = User::where('email', 'marcel@exemple.com')->first();
    expect($user->thematiquesChoisies()->where('thematiques.id', $thematique->id)->exists())->toBeTrue();

    $pivot = $user->etablissementsChoisis()->first();
    expect($pivot->id)->toBe($cegep->id);
});

test('la session est effacée après la soumission de l\'étape 2', function () {
    $cegep = Etablissement::create(['nom' => 'Cégep Session', 'ville' => 'Sherbrooke']);

    $step1 = [
        'prenom' => 'Henriette',
        'nom' => 'Lafleur',
        'email' => 'henriette@exemple.com',
        'password' => 'Motdepasse1!',
        'choix' => [['etablissement_id' => $cegep->id, 'theme_libre' => 'Mon vécu', 'thematique_ids' => []]],
        'description' => 'Description.',
    ];

    $this->withSession(['inscription_temoin_step1' => $step1])
        ->post(route('inscription.temoin.engagements.store'), [
            'engagements' => array_fill(0, InscriptionTemoinController::NB_ENGAGEMENTS, true),
            'signature' => 'Henriette Lafleur',
        ])
        ->assertSessionMissing('inscription_temoin_step1');
});

// ─── Étape 2 : validation ─────────────────────────────────────────────────────

test('un engagement manquant est rejeté à l\'étape 2', function () {
    $step1 = ['prenom' => 'Test', 'nom' => 'Test', 'email' => 't@t.com', 'password' => 'pwd', 'choix' => [], 'description' => 'x'];

    $engagements = array_fill(0, InscriptionTemoinController::NB_ENGAGEMENTS, true);
    $engagements[2] = false; // Un engagement non coché

    $this->withSession(['inscription_temoin_step1' => $step1])
        ->post(route('inscription.temoin.engagements.store'), [
            'engagements' => $engagements,
            'signature' => 'Test Test',
        ])
        ->assertSessionHasErrors('engagements.2');
});

test('la signature est obligatoire à l\'étape 2', function () {
    $step1 = ['prenom' => 'Test', 'nom' => 'Test', 'email' => 't@t.com', 'password' => 'pwd', 'choix' => [], 'description' => 'x'];

    $this->withSession(['inscription_temoin_step1' => $step1])
        ->post(route('inscription.temoin.engagements.store'), [
            'engagements' => array_fill(0, InscriptionTemoinController::NB_ENGAGEMENTS, true),
            'signature' => '',
        ])
        ->assertSessionHasErrors('signature');
});

test('la signature doit contenir au moins 3 caractères', function () {
    $step1 = ['prenom' => 'Test', 'nom' => 'Test', 'email' => 't@t.com', 'password' => 'pwd', 'choix' => [], 'description' => 'x'];

    $this->withSession(['inscription_temoin_step1' => $step1])
        ->post(route('inscription.temoin.engagements.store'), [
            'engagements' => array_fill(0, InscriptionTemoinController::NB_ENGAGEMENTS, true),
            'signature' => 'AB',
        ])
        ->assertSessionHasErrors('signature');
});

test('l\'étape 2 sans session redirige vers l\'étape 1', function () {
    $this->post(route('inscription.temoin.engagements.store'), [
        'engagements' => array_fill(0, InscriptionTemoinController::NB_ENGAGEMENTS, true),
        'signature' => 'Test Nom',
    ])->assertRedirect(route('inscription.temoin'));
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
