<?php

use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\Thematique;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function creerContexteAssignerTemoin(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    $cours = Cours::create([
        'nom_cours' => 'Histoire',
        'description' => 'Test',
        'code' => '330-TST',
        'groupe' => 'A',
        'enseignant_id' => $enseignant->id,
    ]);

    $etudiant = User::factory()->create(['role' => 'etudiant']);

    $section = Classe::create(['cours_id' => $cours->id]);
    $section->etudiants()->attach($etudiant->id);

    $classe = Groupe::create([
        'classe_id' => $section->id,
        'created_by' => $etudiant->id,
    ]);
    $classe->membres()->attach($etudiant->id);

    $temoin = User::factory()->create([
        'role' => 'personne_agee',
        'statut' => 'actif',
        'email_verified_at' => now(),
    ]);

    return compact('enseignant', 'cours', 'etudiant', 'section', 'classe', 'temoin');
}

// ─── assignerTemoin() ─────────────────────────────────────────────────────────

test('un enseignant peut assigner un témoin actif à sa classe', function () {
    $ctx = creerContexteAssignerTemoin();

    $this->actingAs($ctx['enseignant'])
        ->put(route('groupes.temoin.update', [$ctx['cours'], $ctx['section'], $ctx['classe']]), [
            'personne_agee_id' => $ctx['temoin']->id,
        ])
        ->assertRedirect();

    expect($ctx['classe']->fresh()->personne_agee_id)->toBe($ctx['temoin']->id);
});

test('un enseignant peut désassigner un témoin (null)', function () {
    $ctx = creerContexteAssignerTemoin();
    $ctx['classe']->update(['personne_agee_id' => $ctx['temoin']->id]);

    $this->actingAs($ctx['enseignant'])
        ->put(route('groupes.temoin.update', [$ctx['cours'], $ctx['section'], $ctx['classe']]), [
            'personne_agee_id' => null,
        ])
        ->assertRedirect();

    expect($ctx['classe']->fresh()->personne_agee_id)->toBeNull();
});

test('un étudiant ne peut pas assigner de témoin (redirigé par EnsureRole)', function () {
    $ctx = creerContexteAssignerTemoin();

    // EnsureRole redirige vers /dashboard pour les rôles non autorisés
    $this->actingAs($ctx['etudiant'])
        ->put(route('groupes.temoin.update', [$ctx['cours'], $ctx['section'], $ctx['classe']]), [
            'personne_agee_id' => $ctx['temoin']->id,
        ])
        ->assertRedirect(route('dashboard'));
});

test('on ne peut pas assigner un utilisateur dont le rôle n\'est pas personne_agee', function () {
    $ctx = creerContexteAssignerTemoin();
    $autreEtudiant = User::factory()->create(['role' => 'etudiant']);

    $this->actingAs($ctx['enseignant'])
        ->put(route('groupes.temoin.update', [$ctx['cours'], $ctx['section'], $ctx['classe']]), [
            'personne_agee_id' => $autreEtudiant->id,
        ])
        ->assertSessionHasErrors();
});

test('on ne peut pas assigner une personne âgée en attente', function () {
    $ctx = creerContexteAssignerTemoin();
    $temoinEnAttente = User::factory()->create([
        'role' => 'personne_agee',
        'statut' => 'en_attente',
    ]);

    $this->actingAs($ctx['enseignant'])
        ->put(route('groupes.temoin.update', [$ctx['cours'], $ctx['section'], $ctx['classe']]), [
            'personne_agee_id' => $temoinEnAttente->id,
        ])
        ->assertSessionHasErrors();
});

test('un enseignant d\'un autre cours ne peut pas assigner de témoin', function () {
    $ctx = creerContexteAssignerTemoin();
    $autreEnseignant = User::factory()->create(['role' => 'enseignant']);

    $this->actingAs($autreEnseignant)
        ->put(route('groupes.temoin.update', [$ctx['cours'], $ctx['section'], $ctx['classe']]), [
            'personne_agee_id' => $ctx['temoin']->id,
        ])
        ->assertForbidden();
});

// ─── Filtrage des temoinsDisponibles par thématique ────────────────────────────

test('show() retourne uniquement les témoins partageant une thématique avec la classe', function () {
    $ctx = creerContexteAssignerTemoin();

    $thematique = Thematique::create(['nom' => 'La Nouvelle-France', 'enseignant_id' => $ctx['enseignant']->id]);
    $ctx['classe']->thematiques()->attach($thematique->id);

    // Témoin avec la bonne thématique
    $ctx['temoin']->thematiquesChoisies()->attach($thematique->id);

    // Témoin sans aucune thématique commune
    User::factory()->create(['role' => 'personne_agee', 'statut' => 'actif', 'email_verified_at' => now()]);

    $this->actingAs($ctx['enseignant'])
        ->get(route('groupes.show', [$ctx['cours'], $ctx['section'], $ctx['classe']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('temoinsDisponibles', 1)
            ->where('temoinsDisponibles.0.id', $ctx['temoin']->id)
        );
});

test('show() exclut les témoins dont la thématique ne correspond pas à la classe', function () {
    $ctx = creerContexteAssignerTemoin();

    $thematique = Thematique::create(['nom' => 'La Révolution tranquille', 'enseignant_id' => $ctx['enseignant']->id]);
    $autreThematique = Thematique::create(['nom' => 'Les Premières Nations', 'enseignant_id' => $ctx['enseignant']->id]);
    $ctx['classe']->thematiques()->attach($thematique->id);

    // Témoin avec une thématique différente
    $ctx['temoin']->thematiquesChoisies()->attach($autreThematique->id);

    $this->actingAs($ctx['enseignant'])
        ->get(route('groupes.show', [$ctx['cours'], $ctx['section'], $ctx['classe']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('temoinsDisponibles', 0)
        );
});

test('show() retourne tous les témoins actifs si la classe n\'a aucune thématique', function () {
    $ctx = creerContexteAssignerTemoin();

    // Pas de thématique sur la classe
    User::factory()->create(['role' => 'personne_agee', 'statut' => 'actif', 'email_verified_at' => now()]);
    User::factory()->create(['role' => 'personne_agee', 'statut' => 'actif', 'email_verified_at' => now()]);

    // 3 PAs actives au total ($ctx['temoin'] + 2 ci-dessus)
    $this->actingAs($ctx['enseignant'])
        ->get(route('groupes.show', [$ctx['cours'], $ctx['section'], $ctx['classe']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('temoinsDisponibles', 3)
        );
});
