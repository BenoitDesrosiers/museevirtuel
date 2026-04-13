<?php

use App\Models\Thematique;
use App\Models\User;

test('un enseignant peut voir la fiche d\'un témoin lié à ses thématiques', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $thematique = Thematique::create(['nom' => 'La Nouvelle-France', 'enseignant_id' => $enseignant->id]);

    $pa = User::factory()->create([
        'role' => 'personne_agee',
        'statut' => 'en_attente',
        'description' => 'Vécu pendant la guerre',
        'theme_libre' => null,
    ]);
    $pa->thematiquesChoisies()->attach($thematique->id);

    $this->actingAs($enseignant)
        ->get(route('enseignant.temoins.show', $pa))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Enseignant/TemoinShow')
            ->where('temoin.id', $pa->id)
            ->where('temoin.prenom', $pa->prenom)
            ->where('temoin.statut', 'en_attente')
            ->has('temoin.thematiques_choisies', 1)
        );
});

test('un enseignant peut voir la fiche d\'un témoin approuvé lié à ses thématiques', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $thematique = Thematique::create(['nom' => 'Québec moderne', 'enseignant_id' => $enseignant->id]);

    $pa = User::factory()->create([
        'role' => 'personne_agee',
        'statut' => 'actif',
        'email_verified_at' => now(),
    ]);
    $pa->thematiquesChoisies()->attach($thematique->id);

    $this->actingAs($enseignant)
        ->get(route('enseignant.temoins.show', $pa))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Enseignant/TemoinShow')
            ->where('temoin.statut', 'actif')
        );
});

test('un enseignant ne peut pas voir la fiche d\'un témoin sans thématique commune', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $autreEnseignant = User::factory()->create(['role' => 'enseignant']);
    $thematique = Thematique::create(['nom' => 'Autre thème', 'enseignant_id' => $autreEnseignant->id]);

    $pa = User::factory()->create([
        'role' => 'personne_agee',
        'statut' => 'en_attente',
    ]);
    $pa->thematiquesChoisies()->attach($thematique->id);

    $this->actingAs($enseignant)
        ->get(route('enseignant.temoins.show', $pa))
        ->assertForbidden();
});

test('un enseignant ne peut pas voir la fiche d\'un utilisateur qui n\'est pas personne_agee', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $etudiant = User::factory()->create(['role' => 'etudiant']);

    $this->actingAs($enseignant)
        ->get(route('enseignant.temoins.show', $etudiant))
        ->assertForbidden();
});

test('un invité est redirigé vers /login', function () {
    $pa = User::factory()->create(['role' => 'personne_agee', 'statut' => 'en_attente']);

    $this->get(route('enseignant.temoins.show', $pa))
        ->assertRedirect(route('login'));
});

// ─── Désapprobation ────────────────────────────────────────────────────────────

test('un enseignant peut désapprouver un témoin qu\'il a approuvé', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $thematique = Thematique::create(['nom' => 'Histoire du Québec', 'enseignant_id' => $enseignant->id]);

    $pa = User::factory()->create([
        'role' => 'personne_agee',
        'statut' => 'actif',
        'approuve_par_id' => $enseignant->id,
        'email_verified_at' => now(),
    ]);
    $pa->thematiquesChoisies()->attach($thematique->id);

    $this->actingAs($enseignant)
        ->put(route('enseignant.temoins.desapprouver', $pa))
        ->assertRedirect();

    $pa->refresh();

    expect($pa->statut)->toBe('en_attente')
        ->and($pa->approuve_par_id)->toBeNull()
        ->and($pa->email_verified_at)->toBeNull();
});

test('un enseignant ne peut pas désapprouver un témoin encore en attente', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $thematique = Thematique::create(['nom' => 'Thème X', 'enseignant_id' => $enseignant->id]);

    $pa = User::factory()->create(['role' => 'personne_agee', 'statut' => 'en_attente']);
    $pa->thematiquesChoisies()->attach($thematique->id);

    $this->actingAs($enseignant)
        ->put(route('enseignant.temoins.desapprouver', $pa))
        ->assertForbidden();
});

test('un enseignant ne peut pas désapprouver un témoin approuvé par un autre enseignant', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $autreEnseignant = User::factory()->create(['role' => 'enseignant']);
    $thematique = Thematique::create(['nom' => 'Thème Y', 'enseignant_id' => $autreEnseignant->id]);

    $pa = User::factory()->create([
        'role' => 'personne_agee',
        'statut' => 'actif',
        'approuve_par_id' => $autreEnseignant->id,
        'email_verified_at' => now(),
    ]);
    $pa->thematiquesChoisies()->attach($thematique->id);

    $this->actingAs($enseignant)
        ->put(route('enseignant.temoins.desapprouver', $pa))
        ->assertForbidden();
});

// ─── Approbation enseignant : approuve_par_id ─────────────────────────────────

test('un enseignant qui approuve un témoin définit approuve_par_id', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $thematique = Thematique::create(['nom' => 'Thème Z', 'enseignant_id' => $enseignant->id]);

    $pa = User::factory()->create([
        'role' => 'personne_agee',
        'statut' => 'en_attente',
        'email_verified_at' => null,
    ]);
    $pa->thematiquesChoisies()->attach($thematique->id);

    $this->actingAs($enseignant)
        ->put(route('enseignant.temoins.approuver', $pa))
        ->assertRedirect();

    $pa->refresh();

    expect($pa->statut)->toBe('actif')
        ->and($pa->approuve_par_id)->toBe($enseignant->id)
        ->and($pa->email_verified_at)->not->toBeNull();
});

test('un enseignant approuvant un PA le retire de la liste des témoins en attente des autres enseignants', function () {
    $enseignantA = User::factory()->create(['role' => 'enseignant']);
    $enseignantB = User::factory()->create(['role' => 'enseignant']);
    $thematiqueA = Thematique::create(['nom' => 'Thème A', 'enseignant_id' => $enseignantA->id]);
    $thematiqueB = Thematique::create(['nom' => 'Thème B', 'enseignant_id' => $enseignantB->id]);

    $pa = User::factory()->create(['role' => 'personne_agee', 'statut' => 'en_attente']);
    $pa->thematiquesChoisies()->attach([$thematiqueA->id, $thematiqueB->id]);

    // Enseignant A approuve
    $this->actingAs($enseignantA)
        ->put(route('enseignant.temoins.approuver', $pa))
        ->assertRedirect();

    // Enseignant A voit 1 approuvé
    $this->actingAs($enseignantA)
        ->get(route('enseignant.index'))
        ->assertInertia(fn ($page) => $page
            ->has('temoinsApprouves', 1)
            ->has('temoinsEnAttente', 0)
        );

    // Enseignant B ne voit plus le PA du tout (ni en attente ni approuvé)
    $this->actingAs($enseignantB)
        ->get(route('enseignant.index'))
        ->assertInertia(fn ($page) => $page
            ->has('temoinsApprouves', 0)
            ->has('temoinsEnAttente', 0)
        );
});
