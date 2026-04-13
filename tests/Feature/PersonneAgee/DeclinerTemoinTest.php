<?php

use App\Models\Thematique;
use App\Models\User;

// ─── Admin : décliner ──────────────────────────────────────────────────────────

test('un admin peut décliner un témoin en attente', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $pa = User::factory()->create([
        'role' => 'personne_agee',
        'statut' => 'en_attente',
    ]);

    $this->actingAs($admin)
        ->put(route('administration.temoins.decliner', $pa))
        ->assertRedirect();

    expect($pa->fresh()->statut)->toBe('refuse');
});

test('un non-admin est redirigé s\'il tente de décliner un témoin via admin', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $pa = User::factory()->create([
        'role' => 'personne_agee',
        'statut' => 'en_attente',
    ]);

    $this->actingAs($enseignant)
        ->put(route('administration.temoins.decliner', $pa))
        ->assertRedirect(route('dashboard'));
});

test('un admin ne peut pas décliner un utilisateur qui n\'est pas personne_agee', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $etudiant = User::factory()->create(['role' => 'etudiant', 'statut' => 'actif']);

    $this->actingAs($admin)
        ->put(route('administration.temoins.decliner', $etudiant))
        ->assertForbidden();
});

test('un admin ne peut pas décliner un témoin déjà actif', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $pa = User::factory()->create([
        'role' => 'personne_agee',
        'statut' => 'actif',
    ]);

    $this->actingAs($admin)
        ->put(route('administration.temoins.decliner', $pa))
        ->assertForbidden();
});

// ─── Enseignant : décliner ─────────────────────────────────────────────────────

test('un enseignant peut décliner un témoin lié à ses thématiques', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $thematique = Thematique::create(['nom' => 'La Nouvelle-France', 'enseignant_id' => $enseignant->id]);

    $pa = User::factory()->create([
        'role' => 'personne_agee',
        'statut' => 'en_attente',
    ]);
    $pa->thematiquesChoisies()->attach($thematique->id);

    $this->actingAs($enseignant)
        ->put(route('enseignant.temoins.decliner', $pa))
        ->assertRedirect();

    expect($pa->fresh()->statut)->toBe('refuse');
});

test('un enseignant ne peut pas décliner un témoin sans thématique commune', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $autreEnseignant = User::factory()->create(['role' => 'enseignant']);
    $thematique = Thematique::create(['nom' => 'Autre thème', 'enseignant_id' => $autreEnseignant->id]);

    $pa = User::factory()->create([
        'role' => 'personne_agee',
        'statut' => 'en_attente',
    ]);
    $pa->thematiquesChoisies()->attach($thematique->id);

    $this->actingAs($enseignant)
        ->put(route('enseignant.temoins.decliner', $pa))
        ->assertForbidden();
});

test('un enseignant ne peut pas décliner un témoin déjà actif', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $thematique = Thematique::create(['nom' => 'Thème test', 'enseignant_id' => $enseignant->id]);

    $pa = User::factory()->create([
        'role' => 'personne_agee',
        'statut' => 'actif',
    ]);
    $pa->thematiquesChoisies()->attach($thematique->id);

    $this->actingAs($enseignant)
        ->put(route('enseignant.temoins.decliner', $pa))
        ->assertForbidden();
});

test('un invité est redirigé vers /login s\'il tente de décliner', function () {
    $pa = User::factory()->create([
        'role' => 'personne_agee',
        'statut' => 'en_attente',
    ]);

    $this->put(route('enseignant.temoins.decliner', $pa))
        ->assertRedirect(route('login'));
});

// ─── Index enseignant : prop temoinsApprouves ─────────────────────────────────

test('l\'index enseignant expose uniquement les témoins approuvés par cet enseignant', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $autreEnseignant = User::factory()->create(['role' => 'enseignant']);
    $thematique = Thematique::create(['nom' => 'Histoire', 'enseignant_id' => $enseignant->id]);

    // Approuvé par cet enseignant → doit apparaître
    $paApprouvePar = User::factory()->create([
        'role' => 'personne_agee',
        'statut' => 'actif',
        'approuve_par_id' => $enseignant->id,
        'email_verified_at' => now(),
    ]);
    $paApprouvePar->thematiquesChoisies()->attach($thematique->id);

    // Approuvé par un autre enseignant → ne doit pas apparaître
    $paApprouveAutre = User::factory()->create([
        'role' => 'personne_agee',
        'statut' => 'actif',
        'approuve_par_id' => $autreEnseignant->id,
        'email_verified_at' => now(),
    ]);
    $paApprouveAutre->thematiquesChoisies()->attach($thematique->id);

    $paEnAttente = User::factory()->create([
        'role' => 'personne_agee',
        'statut' => 'en_attente',
    ]);
    $paEnAttente->thematiquesChoisies()->attach($thematique->id);

    $this->actingAs($enseignant)
        ->get(route('enseignant.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('temoinsApprouves', 1)
            ->where('temoinsApprouves.0.id', $paApprouvePar->id)
            ->has('temoinsEnAttente', 1)
            ->where('temoinsEnAttente.0.id', $paEnAttente->id)
        );
});
