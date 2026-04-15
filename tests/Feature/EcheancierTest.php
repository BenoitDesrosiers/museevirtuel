<?php

use App\Models\Cours;
use App\Models\EcheancierEtape;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function creerContexteEcheancier(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = Cours::create([
        'nom_cours' => 'Histoire',
        'code' => 'HIS101',
        'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);

    $etudiant = User::factory()->create(['role' => 'etudiant']);
    $cours->etudiants()->attach($etudiant->id);

    $etape = EcheancierEtape::create([
        'cours_id' => $cours->id,
        'semaine' => 1,
        'etape' => 'Lire le chapitre 1',
        'is_done' => false,
        'ordre' => 0,
    ]);

    return compact('enseignant', 'cours', 'etudiant', 'etape');
}

// ─── destroyAll() ─────────────────────────────────────────────────────────────

test('destroyAll() supprime toutes les étapes de la classe', function () {
    $ctx = creerContexteEcheancier();

    EcheancierEtape::create([
        'cours_id' => $ctx['cours']->id,
        'semaine' => 2,
        'etape' => 'Rédiger un résumé',
        'is_done' => false,
        'ordre' => 0,
    ]);

    expect(EcheancierEtape::where('cours_id', $ctx['cours']->id)->count())->toBe(2);

    $this->actingAs($ctx['enseignant'])
        ->delete("/cours/{$ctx['cours']->id}/echeancier")
        ->assertRedirect();

    expect(EcheancierEtape::where('cours_id', $ctx['cours']->id)->count())->toBe(0);
});

test('destroyAll() ne supprime pas les étapes des autres cours', function () {
    $ctx = creerContexteEcheancier();

    $autreEnseignant = User::factory()->create(['role' => 'enseignant']);
    $autreCours = Cours::create([
        'nom_cours' => 'Maths',
        'code' => 'MAT201',
        'groupe' => '01',
        'enseignant_id' => $autreEnseignant->id,
    ]);
    $etapeAutre = EcheancierEtape::create([
        'cours_id' => $autreCours->id,
        'semaine' => 1,
        'etape' => 'Exercices',
        'is_done' => false,
        'ordre' => 0,
    ]);

    $this->actingAs($ctx['enseignant'])
        ->delete("/cours/{$ctx['cours']->id}/echeancier")
        ->assertRedirect();

    $this->assertDatabaseHas('echeancier_etapes', ['id' => $etapeAutre->id]);
});

// ─── toggleEtudiant() ─────────────────────────────────────────────────────────

test('toggleEtudiant() coche la progression personnelle de l\'étudiant', function () {
    $ctx = creerContexteEcheancier();

    $this->actingAs($ctx['etudiant'])
        ->patch("/cours/{$ctx['cours']->id}/echeancier/{$ctx['etape']->id}/toggle-etudiant")
        ->assertRedirect();

    $this->assertDatabaseHas('echeancier_etudiant_progress', [
        'echeancier_etape_id' => $ctx['etape']->id,
        'user_id' => $ctx['etudiant']->id,
        'is_done' => true,
    ]);
});

test('toggleEtudiant() décoche si déjà coché', function () {
    $ctx = creerContexteEcheancier();

    // Premier toggle : coche
    $this->actingAs($ctx['etudiant'])
        ->patch("/cours/{$ctx['cours']->id}/echeancier/{$ctx['etape']->id}/toggle-etudiant");

    // Deuxième toggle : décoche
    $this->actingAs($ctx['etudiant'])
        ->patch("/cours/{$ctx['cours']->id}/echeancier/{$ctx['etape']->id}/toggle-etudiant")
        ->assertRedirect();

    $this->assertDatabaseHas('echeancier_etudiant_progress', [
        'echeancier_etape_id' => $ctx['etape']->id,
        'user_id' => $ctx['etudiant']->id,
        'is_done' => false,
    ]);
});

test('toggleEtudiant() — la progression est isolée par utilisateur', function () {
    $ctx = creerContexteEcheancier();
    $autreEtudiant = User::factory()->create(['role' => 'etudiant']);
    $ctx['cours']->etudiants()->attach($autreEtudiant->id);

    // Alice coche
    $this->actingAs($ctx['etudiant'])
        ->patch("/cours/{$ctx['cours']->id}/echeancier/{$ctx['etape']->id}/toggle-etudiant");

    // La progression de l'autre étudiant doit rester intacte (pas d'entrée)
    $this->assertDatabaseMissing('echeancier_etudiant_progress', [
        'echeancier_etape_id' => $ctx['etape']->id,
        'user_id' => $autreEtudiant->id,
    ]);
});

test('toggleEtudiant() refuse un étudiant non inscrit dans le cours (403)', function () {
    $ctx = creerContexteEcheancier();
    $etranger = User::factory()->create(['role' => 'etudiant']);

    $this->actingAs($etranger)
        ->patch("/cours/{$ctx['cours']->id}/echeancier/{$ctx['etape']->id}/toggle-etudiant")
        ->assertForbidden();
});
