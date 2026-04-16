<?php

use App\Models\Classe;
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

    $section = Classe::create(['cours_id' => $cours->id]);
    $section->etudiants()->attach($etudiant->id);

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
        'code' => 'MAT101',
        'groupe' => '01',
        'enseignant_id' => $autreEnseignant->id,
    ]);
    EcheancierEtape::create([
        'cours_id' => $autreCours->id,
        'semaine' => 1,
        'etape' => 'Autre cours',
        'is_done' => false,
        'ordre' => 0,
    ]);

    $this->actingAs($ctx['enseignant'])
        ->delete("/cours/{$ctx['cours']->id}/echeancier")
        ->assertRedirect();

    expect(EcheancierEtape::where('cours_id', $autreCours->id)->count())->toBe(1);
});
