<?php

use App\Models\Cours;
use App\Models\User;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Retourne un payload valide pour la création d'un cours.
 *
 * @return array<string, mixed>
 */
function payloadCoursValide(): array
{
    return [
        'nom_cours' => 'Histoire du Québec',
        'code' => '330-HIS',
        'groupe' => '01',
        'annee' => 2026,
        'session' => 'hiver',
        'type_cours' => 'cours_complet',
    ];
}

// ─── store() — validation ──────────────────────────────────────────────────────

test('store accepte un cours avec annee et session valides', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    $this->actingAs($enseignant)
        ->post('/cours', payloadCoursValide())
        ->assertRedirect();

    $this->assertDatabaseHas('cours', [
        'code' => '330-HIS',
        'annee' => 2026,
        'session' => 'hiver',
        'enseignant_id' => $enseignant->id,
    ]);
});

test('store rejette un cours sans annee', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $payload = payloadCoursValide();
    unset($payload['annee']);

    $this->actingAs($enseignant)
        ->post('/cours', $payload)
        ->assertSessionHasErrors('annee');
});

test('store rejette un cours sans session', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $payload = payloadCoursValide();
    unset($payload['session']);

    $this->actingAs($enseignant)
        ->post('/cours', $payload)
        ->assertSessionHasErrors('session');
});

test('store rejette une session invalide', function (string $sessionInvalide) {
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    $this->actingAs($enseignant)
        ->post('/cours', array_merge(payloadCoursValide(), ['session' => $sessionInvalide]))
        ->assertSessionHasErrors('session');
})->with(['printemps', 'winter', '', '123']);

test('store accepte toutes les sessions valides', function (string $session) {
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    $this->actingAs($enseignant)
        ->post('/cours', array_merge(payloadCoursValide(), [
            'session' => $session,
            'code' => '330-'.strtoupper($session),
        ]))
        ->assertRedirect();

    $this->assertDatabaseHas('cours', [
        'code' => '330-'.strtoupper($session),
        'session' => $session,
    ]);
})->with(['hiver', 'ete', 'automne']);

// ─── update() — validation ────────────────────────────────────────────────────

test('update met a jour annee et session', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    $cours = Cours::create([
        'nom_cours' => 'Cours original',
        'code' => '330-UPD',
        'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);

    $this->actingAs($enseignant)
        ->put("/cours/{$cours->id}", array_merge(payloadCoursValide(), [
            'code' => '330-UPD',
            'annee' => 2027,
            'session' => 'automne',
        ]))
        ->assertRedirect();

    $this->assertDatabaseHas('cours', [
        'id' => $cours->id,
        'annee' => 2027,
        'session' => 'automne',
    ]);
});
