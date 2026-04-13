<?php

use App\Models\Classe;
use App\Models\Groupe;
use App\Models\Thematique;
use App\Models\User;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function creerContexteEchanges(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    $classe = Classe::create([
        'nom_cours' => 'Histoire',
        'description' => 'Test',
        'code' => '330-TST',
        'groupe' => 'A',
        'enseignant_id' => $enseignant->id,
    ]);

    $etudiant = User::factory()->create(['role' => 'etudiant']);
    $classe->etudiants()->attach($etudiant->id);

    $groupe = Groupe::create([
        'classe_id' => $classe->id,
        'created_by' => $etudiant->id,
    ]);
    $groupe->membres()->attach($etudiant->id);

    $temoin = User::factory()->create([
        'role' => 'personne_agee',
        'statut' => 'actif',
        'email_verified_at' => now(),
    ]);

    return compact('enseignant', 'classe', 'etudiant', 'groupe', 'temoin');
}

// ─── assignerTemoin() ─────────────────────────────────────────────────────────

test('un enseignant peut assigner un témoin actif à son groupe', function () {
    $ctx = creerContexteEchanges();

    $this->actingAs($ctx['enseignant'])
        ->put(route('groupes.temoin.update', [$ctx['classe'], $ctx['groupe']]), [
            'personne_agee_id' => $ctx['temoin']->id,
        ])
        ->assertRedirect();

    expect($ctx['groupe']->fresh()->personne_agee_id)->toBe($ctx['temoin']->id);
});

test('un enseignant peut désassigner un témoin (null)', function () {
    $ctx = creerContexteEchanges();
    $ctx['groupe']->update(['personne_agee_id' => $ctx['temoin']->id]);

    $this->actingAs($ctx['enseignant'])
        ->put(route('groupes.temoin.update', [$ctx['classe'], $ctx['groupe']]), [
            'personne_agee_id' => null,
        ])
        ->assertRedirect();

    expect($ctx['groupe']->fresh()->personne_agee_id)->toBeNull();
});

test('un étudiant ne peut pas assigner de témoin (redirigé par EnsureRole)', function () {
    $ctx = creerContexteEchanges();

    // EnsureRole redirige vers /dashboard pour les rôles non autorisés
    $this->actingAs($ctx['etudiant'])
        ->put(route('groupes.temoin.update', [$ctx['classe'], $ctx['groupe']]), [
            'personne_agee_id' => $ctx['temoin']->id,
        ])
        ->assertRedirect(route('dashboard'));
});

test('on ne peut pas assigner un utilisateur dont le rôle n\'est pas personne_agee', function () {
    $ctx = creerContexteEchanges();
    $autrEtudiant = User::factory()->create(['role' => 'etudiant']);

    $this->actingAs($ctx['enseignant'])
        ->put(route('groupes.temoin.update', [$ctx['classe'], $ctx['groupe']]), [
            'personne_agee_id' => $autrEtudiant->id,
        ])
        ->assertSessionHasErrors();
});

test('on ne peut pas assigner une personne âgée en attente', function () {
    $ctx = creerContexteEchanges();
    $temoinEnAttente = User::factory()->create([
        'role' => 'personne_agee',
        'statut' => 'en_attente',
    ]);

    $this->actingAs($ctx['enseignant'])
        ->put(route('groupes.temoin.update', [$ctx['classe'], $ctx['groupe']]), [
            'personne_agee_id' => $temoinEnAttente->id,
        ])
        ->assertSessionHasErrors();
});

test('un enseignant d\'une autre classe ne peut pas assigner de témoin', function () {
    $ctx = creerContexteEchanges();
    $autreEnseignant = User::factory()->create(['role' => 'enseignant']);

    $this->actingAs($autreEnseignant)
        ->put(route('groupes.temoin.update', [$ctx['classe'], $ctx['groupe']]), [
            'personne_agee_id' => $ctx['temoin']->id,
        ])
        ->assertForbidden();
});

// ─── Filtrage des temoinsDisponibles par thématique ────────────────────────────

test('show() retourne uniquement les témoins partageant une thématique avec le groupe', function () {
    $ctx = creerContexteEchanges();

    $thematique = Thematique::create(['nom' => 'La Nouvelle-France', 'enseignant_id' => $ctx['enseignant']->id]);
    $ctx['groupe']->thematiques()->attach($thematique->id);

    // Témoin avec la bonne thématique
    $ctx['temoin']->thematiquesChoisies()->attach($thematique->id);

    // Témoin sans aucune thématique commune
    User::factory()->create(['role' => 'personne_agee', 'statut' => 'actif', 'email_verified_at' => now()]);

    $this->actingAs($ctx['enseignant'])
        ->get(route('groupes.show', [$ctx['classe'], $ctx['groupe']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('temoinsDisponibles', 1)
            ->where('temoinsDisponibles.0.id', $ctx['temoin']->id)
        );
});

test('show() exclut les témoins dont la thématique ne correspond pas au groupe', function () {
    $ctx = creerContexteEchanges();

    $thematique = Thematique::create(['nom' => 'La Révolution tranquille', 'enseignant_id' => $ctx['enseignant']->id]);
    $autreThematique = Thematique::create(['nom' => 'Les Premières Nations', 'enseignant_id' => $ctx['enseignant']->id]);
    $ctx['groupe']->thematiques()->attach($thematique->id);

    // Témoin avec une thématique différente
    $ctx['temoin']->thematiquesChoisies()->attach($autreThematique->id);

    $this->actingAs($ctx['enseignant'])
        ->get(route('groupes.show', [$ctx['classe'], $ctx['groupe']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('temoinsDisponibles', 0)
        );
});

test('show() retourne tous les témoins actifs si le groupe n\'a aucune thématique', function () {
    $ctx = creerContexteEchanges();

    // Pas de thématique sur le groupe
    User::factory()->create(['role' => 'personne_agee', 'statut' => 'actif', 'email_verified_at' => now()]);
    User::factory()->create(['role' => 'personne_agee', 'statut' => 'actif', 'email_verified_at' => now()]);

    // 3 PAs actives au total ($ctx['temoin'] + 2 ci-dessus)
    $this->actingAs($ctx['enseignant'])
        ->get(route('groupes.show', [$ctx['classe'], $ctx['groupe']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('temoinsDisponibles', 3)
        );
});
