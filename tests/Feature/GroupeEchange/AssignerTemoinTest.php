<?php

use App\Models\Classe;
use App\Models\Groupe;
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
