<?php

use App\Models\Cours;
use App\Models\CoursLienEntrevue;
use App\Models\User;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Crée un enseignant + cours minimal pour les tests de liens d'entrevue.
 *
 * @return array{enseignant: User, cours: Cours}
 */
function creerScenarioLiens(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    $cours = Cours::create([
        'nom_cours' => 'Cours liens entrevue',
        'code' => '330-LNK',
        'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);

    return compact('enseignant', 'cours');
}

/** URL de base pour les liens d'entrevue du cours. */
function urlLiens(Cours $cours): string
{
    return "/cours/{$cours->id}/liens-entrevue";
}

// ─── store() ──────────────────────────────────────────────────────────────────

test("l'enseignant peut ajouter un lien d'entrevue à son cours", function () {
    $s = creerScenarioLiens();

    $this->actingAs($s['enseignant'])
        ->post(urlLiens($s['cours']), [
            'label' => 'Vidéo de référence',
            'url' => 'https://example.com/video',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('cours_liens_entrevue', [
        'cours_id' => $s['cours']->id,
        'label' => 'Vidéo de référence',
        'url' => 'https://example.com/video',
    ]);
});

test("l'ordre est incrémenté automatiquement à l'ajout", function () {
    $s = creerScenarioLiens();

    $this->actingAs($s['enseignant'])
        ->post(urlLiens($s['cours']), ['label' => 'Lien A', 'url' => 'https://example.com/a']);

    $this->actingAs($s['enseignant'])
        ->post(urlLiens($s['cours']), ['label' => 'Lien B', 'url' => 'https://example.com/b']);

    $liens = CoursLienEntrevue::where('cours_id', $s['cours']->id)->orderBy('ordre')->get();
    expect($liens[0]->ordre)->toBe(1);
    expect($liens[1]->ordre)->toBe(2);
});

test('store rejette une URL invalide', function () {
    $s = creerScenarioLiens();

    $this->actingAs($s['enseignant'])
        ->post(urlLiens($s['cours']), [
            'label' => 'Mauvais lien',
            'url' => 'pas-une-url',
        ])
        ->assertSessionHasErrors('url');
});

test("un enseignant d'un autre cours ne peut pas ajouter de liens", function () {
    $s = creerScenarioLiens();
    $autreEnseignant = User::factory()->create(['role' => 'enseignant']);

    $this->actingAs($autreEnseignant)
        ->post(urlLiens($s['cours']), [
            'label' => 'Intrusion',
            'url' => 'https://example.com',
        ])
        ->assertForbidden();
});

// ─── update() ─────────────────────────────────────────────────────────────────

test("l'enseignant peut modifier un lien d'entrevue", function () {
    $s = creerScenarioLiens();

    $lien = CoursLienEntrevue::create([
        'cours_id' => $s['cours']->id,
        'label' => 'Ancien label',
        'url' => 'https://example.com/ancien',
        'ordre' => 1,
    ]);

    $this->actingAs($s['enseignant'])
        ->put(urlLiens($s['cours'])."/{$lien->id}", [
            'label' => 'Nouveau label',
            'url' => 'https://example.com/nouveau',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('cours_liens_entrevue', [
        'id' => $lien->id,
        'label' => 'Nouveau label',
        'url' => 'https://example.com/nouveau',
    ]);
});

// ─── destroy() ────────────────────────────────────────────────────────────────

test("l'enseignant peut supprimer un lien d'entrevue", function () {
    $s = creerScenarioLiens();

    $lien = CoursLienEntrevue::create([
        'cours_id' => $s['cours']->id,
        'label' => 'À supprimer',
        'url' => 'https://example.com',
        'ordre' => 1,
    ]);

    $this->actingAs($s['enseignant'])
        ->delete(urlLiens($s['cours'])."/{$lien->id}")
        ->assertRedirect();

    $this->assertDatabaseMissing('cours_liens_entrevue', ['id' => $lien->id]);
});

test('la suppression renumérote les liens restants', function () {
    $s = creerScenarioLiens();

    $l1 = CoursLienEntrevue::create(['cours_id' => $s['cours']->id, 'label' => 'L1', 'url' => 'https://a.com', 'ordre' => 1]);
    $l2 = CoursLienEntrevue::create(['cours_id' => $s['cours']->id, 'label' => 'L2', 'url' => 'https://b.com', 'ordre' => 2]);
    $l3 = CoursLienEntrevue::create(['cours_id' => $s['cours']->id, 'label' => 'L3', 'url' => 'https://c.com', 'ordre' => 3]);

    $this->actingAs($s['enseignant'])
        ->delete(urlLiens($s['cours'])."/{$l1->id}");

    expect($l2->fresh()->ordre)->toBe(1);
    expect($l3->fresh()->ordre)->toBe(2);
});

// ─── reorder() ────────────────────────────────────────────────────────────────

test("l'enseignant peut réordonner les liens d'entrevue", function () {
    $s = creerScenarioLiens();

    $l1 = CoursLienEntrevue::create(['cours_id' => $s['cours']->id, 'label' => 'L1', 'url' => 'https://a.com', 'ordre' => 1]);
    $l2 = CoursLienEntrevue::create(['cours_id' => $s['cours']->id, 'label' => 'L2', 'url' => 'https://b.com', 'ordre' => 2]);

    $this->actingAs($s['enseignant'])
        ->patch(urlLiens($s['cours']).'/reorder', [
            'ordre' => [$l2->id, $l1->id],
        ])
        ->assertRedirect();

    expect($l2->fresh()->ordre)->toBe(1);
    expect($l1->fresh()->ordre)->toBe(2);
});

// ─── Sécurité — lien appartenant à un autre cours ─────────────────────────────

test('un enseignant ne peut pas modifier un lien appartenant à un autre cours', function () {
    $s = creerScenarioLiens();

    $autreEnseignant = User::factory()->create(['role' => 'enseignant']);
    $autreCours = Cours::create([
        'nom_cours' => 'Autre cours',
        'code' => '330-ZZZ',
        'groupe' => '01',
        'enseignant_id' => $autreEnseignant->id,
    ]);

    $lien = CoursLienEntrevue::create([
        'cours_id' => $autreCours->id,
        'label' => 'Lien d\'un autre cours',
        'url' => 'https://example.com',
        'ordre' => 1,
    ]);

    // Enseignant du cours original tente de modifier un lien d'un autre cours
    $this->actingAs($s['enseignant'])
        ->put("/cours/{$s['cours']->id}/liens-entrevue/{$lien->id}", [
            'label' => 'Tentative',
            'url' => 'https://example.com',
        ])
        ->assertNotFound();
});
