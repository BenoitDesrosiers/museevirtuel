<?php

use App\Models\Cours;
use App\Models\CoursObjectif;
use App\Models\User;

/**
 * Crée un enseignant + cours minimal pour les tests d'objectifs.
 *
 * @return array{enseignant: User, cours: Cours}
 */
function creerScenarioObjectif(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    $cours = Cours::create([
        'nom_cours' => 'Histoire du Québec',
        'code' => '330-OBJ',
        'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);

    return compact('enseignant', 'cours');
}

/** URL de base pour les objectifs du cours. */
function urlObjectifs(Cours $cours): string
{
    return "/cours/{$cours->id}/objectifs";
}

test("l'enseignant peut supprimer un objectif", function () {
    ['enseignant' => $enseignant, 'cours' => $cours] = creerScenarioObjectif();

    $objectif = CoursObjectif::create([
        'cours_id' => $cours->id,
        'contenu' => 'Analyser des sources primaires',
        'ordre' => 1,
    ]);

    $this->actingAs($enseignant)
        ->delete(urlObjectifs($cours)."/{$objectif->id}")
        ->assertRedirect();

    $this->assertDatabaseMissing('cours_objectifs', ['id' => $objectif->id]);
});

test('les ordres sont renumérotés après la suppression d un objectif', function () {
    ['enseignant' => $enseignant, 'cours' => $cours] = creerScenarioObjectif();

    $o1 = CoursObjectif::create(['cours_id' => $cours->id, 'contenu' => 'A', 'ordre' => 1]);
    $o2 = CoursObjectif::create(['cours_id' => $cours->id, 'contenu' => 'B', 'ordre' => 2]);
    $o3 = CoursObjectif::create(['cours_id' => $cours->id, 'contenu' => 'C', 'ordre' => 3]);

    $this->actingAs($enseignant)->delete(urlObjectifs($cours)."/{$o2->id}");

    $ordres = $cours->objectifs()->orderBy('ordre')->pluck('ordre')->all();

    expect($ordres)->toBe([1, 2]);
});

// ─── Réordonnancement commun (ReorderRequest + OrdreElements) ─────────────────

test('reorder applique le nouvel ordre', function () {
    ['enseignant' => $enseignant, 'cours' => $cours] = creerScenarioObjectif();
    $a = CoursObjectif::create(['cours_id' => $cours->id, 'contenu' => 'A', 'ordre' => 1]);
    $b = CoursObjectif::create(['cours_id' => $cours->id, 'contenu' => 'B', 'ordre' => 2]);

    $this->actingAs($enseignant)
        ->patch(urlObjectifs($cours).'/reorder', ['ordre' => [$b->id, $a->id]])
        ->assertRedirect();

    expect($b->fresh()->ordre)->toBe(1)->and($a->fresh()->ordre)->toBe(2);
});

test('reorder refuse un ID en double', function () {
    ['enseignant' => $enseignant, 'cours' => $cours] = creerScenarioObjectif();
    $a = CoursObjectif::create(['cours_id' => $cours->id, 'contenu' => 'A', 'ordre' => 1]);

    $this->actingAs($enseignant)
        ->patchJson(urlObjectifs($cours).'/reorder', ['ordre' => [$a->id, $a->id]])
        ->assertJsonValidationErrors('ordre.0');
});

test("reorder refuse l'objectif d'un autre cours sans rien modifier", function () {
    ['enseignant' => $enseignant, 'cours' => $cours] = creerScenarioObjectif();
    $autreCours = Cours::create(['nom_cours' => 'Autre', 'code' => '330-AUT', 'groupe' => '02', 'enseignant_id' => $enseignant->id]);
    $etranger = CoursObjectif::create(['cours_id' => $autreCours->id, 'contenu' => 'X', 'ordre' => 4]);

    $this->actingAs($enseignant)
        ->patchJson(urlObjectifs($cours).'/reorder', ['ordre' => [$etranger->id]])
        ->assertJsonValidationErrors('ordre');

    expect($etranger->fresh()->ordre)->toBe(4);
});
