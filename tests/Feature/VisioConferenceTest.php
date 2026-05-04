<?php

use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\User;
use App\Models\VisioConference;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Crée un enseignant, un cours, une classe et un groupe liés.
 *
 * @return array{enseignant: User, cours: Cours, classe: Classe, groupe: Groupe}
 */
function creerScenarioVisio(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    $cours = Cours::create([
        'nom_cours' => 'Cours Visio Test',
        'code' => '330-VIS',
        'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);

    $classe = Classe::create(['cours_id' => $cours->id]);

    $etudiant = User::factory()->create(['role' => 'etudiant']);
    $classe->etudiants()->attach($etudiant->id);

    $groupe = Groupe::create([
        'classe_id' => $classe->id,
        'created_by' => $etudiant->id,
    ]);

    return compact('enseignant', 'cours', 'classe', 'groupe');
}

/** URL de base pour les visioconférences d'un cours. */
function urlVisio(Cours $cours, ?VisioConference $visio = null): string
{
    $base = "/cours/{$cours->id}/visio";

    return $visio ? "{$base}/{$visio->id}" : $base;
}

// ─── store() ──────────────────────────────────────────────────────────────────

test("l'enseignant peut créer une visioconférence pour son cours", function () {
    $s = creerScenarioVisio();

    $this->actingAs($s['enseignant'])
        ->post(urlVisio($s['cours']), [
            'titre' => 'Rencontre de lancement',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('visio_conferences', [
        'cours_id' => $s['cours']->id,
        'titre' => 'Rencontre de lancement',
        'animateur_id' => $s['enseignant']->id,
    ]);
});

test('la room Jitsi générée respecte le format XXXX-YYYYYYYY', function () {
    $s = creerScenarioVisio();

    $this->actingAs($s['enseignant'])
        ->post(urlVisio($s['cours']), ['titre' => 'Test room format'])
        ->assertRedirect();

    $visio = VisioConference::where('cours_id', $s['cours']->id)->first();

    // Format attendu : 4 chiffres - 8 caractères
    expect($visio->jitsi_room)->toMatch('/^\d{4}-[A-Za-z0-9]{8}$/');
});

test('deux visios créées ont des rooms distinctes', function () {
    $s = creerScenarioVisio();

    $this->actingAs($s['enseignant'])->post(urlVisio($s['cours']), ['titre' => 'Visio A']);
    $this->actingAs($s['enseignant'])->post(urlVisio($s['cours']), ['titre' => 'Visio B']);

    $rooms = VisioConference::where('cours_id', $s['cours']->id)->pluck('jitsi_room');
    expect($rooms->unique()->count())->toBe(2);
});

test("l'enseignant peut cibler un groupe spécifique lors de la création", function () {
    $s = creerScenarioVisio();

    $this->actingAs($s['enseignant'])
        ->post(urlVisio($s['cours']), [
            'titre' => 'Session ciblée',
            'groupe_id' => $s['groupe']->id,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('visio_conferences', [
        'cours_id' => $s['cours']->id,
        'groupe_id' => $s['groupe']->id,
    ]);
});

test("un enseignant d'un autre cours reçoit 403 (IDOR)", function () {
    $s = creerScenarioVisio();
    $autre = User::factory()->create(['role' => 'enseignant']);

    $this->actingAs($autre)
        ->post(urlVisio($s['cours']), ['titre' => 'Intrusion'])
        ->assertForbidden();
});

test('un étudiant ne peut pas créer de visioconférence (redirigé)', function () {
    $s = creerScenarioVisio();
    $etudiant = User::factory()->create(['role' => 'etudiant']);

    // Le middleware EnsureRole redirige (302) vers dashboard au lieu de 403
    $this->actingAs($etudiant)
        ->post(urlVisio($s['cours']), ['titre' => 'Tentative'])
        ->assertRedirect();

    // Aucune visio créée
    $this->assertDatabaseMissing('visio_conferences', ['cours_id' => $s['cours']->id]);
});

// ─── update() ─────────────────────────────────────────────────────────────────

test("l'enseignant peut modifier le titre et la date planifiée d'une visio", function () {
    $s = creerScenarioVisio();

    $visio = VisioConference::create([
        'cours_id' => $s['cours']->id,
        'animateur_id' => $s['enseignant']->id,
        'jitsi_room' => '0001-AbCdEfGh',
        'titre' => 'Ancien titre',
    ]);

    $this->actingAs($s['enseignant'])
        ->put(urlVisio($s['cours'], $visio), [
            'titre' => 'Nouveau titre',
            'recording_url' => 'https://example.com/rec',
        ])
        ->assertRedirect();

    expect($visio->fresh()->titre)->toBe('Nouveau titre');
    expect($visio->fresh()->recording_url)->toBe('https://example.com/rec');
});

test('update rejette une URL enregistrement invalide', function () {
    $s = creerScenarioVisio();

    $visio = VisioConference::create([
        'cours_id' => $s['cours']->id,
        'animateur_id' => $s['enseignant']->id,
        'jitsi_room' => '0001-XxXxXxXx',
        'titre' => 'Test URL',
    ]);

    $this->actingAs($s['enseignant'])
        ->put(urlVisio($s['cours'], $visio), ['recording_url' => 'pas-une-url'])
        ->assertSessionHasErrors('recording_url');
});

test("update d'une visio appartenant à un autre cours retourne 404", function () {
    $autreEnseignant = User::factory()->create(['role' => 'enseignant']);
    $autreCours = Cours::create([
        'nom_cours' => 'Autre cours',
        'code' => '330-ZZZ',
        'groupe' => '01',
        'enseignant_id' => $autreEnseignant->id,
    ]);

    $s = creerScenarioVisio();

    $visioAutre = VisioConference::create([
        'cours_id' => $autreCours->id,
        'animateur_id' => $autreEnseignant->id,
        'jitsi_room' => '9999-AaBbCcDd',
        'titre' => 'Visio autre cours',
    ]);

    // L'enseignant du cours $s tente de modifier une visio d'un autre cours
    $this->actingAs($s['enseignant'])
        ->put(urlVisio($s['cours'], $visioAutre), ['titre' => 'Intrusion'])
        ->assertNotFound();
});

// ─── destroy() ────────────────────────────────────────────────────────────────

test("l'enseignant peut supprimer une visioconférence de son cours", function () {
    $s = creerScenarioVisio();

    $visio = VisioConference::create([
        'cours_id' => $s['cours']->id,
        'animateur_id' => $s['enseignant']->id,
        'jitsi_room' => '0001-DeLeTe01',
        'titre' => 'À supprimer',
    ]);

    $this->actingAs($s['enseignant'])
        ->delete(urlVisio($s['cours'], $visio))
        ->assertRedirect();

    $this->assertDatabaseMissing('visio_conferences', ['id' => $visio->id]);
});
