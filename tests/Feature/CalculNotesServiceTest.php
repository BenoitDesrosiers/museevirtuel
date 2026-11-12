<?php

use App\Models\ProjetAnnotation;
use App\Models\ProjetCritereCorrection;
use App\Models\TypeProjetCritere;
use App\Services\CalculNotesService;

/**
 * Critère en mémoire (pas besoin de base : le service travaille sur des collections).
 */
function critereNote(int $id, string $type, float $pointage): TypeProjetCritere
{
    return (new TypeProjetCritere)->forceFill(['id' => $id, 'type' => $type, 'pointage' => $pointage]);
}

/**
 * Correction en mémoire.
 */
function correctionNote(int $critereId, ?int $userId, ?float $points, bool $verifie = true): ProjetCritereCorrection
{
    return (new ProjetCritereCorrection)->forceFill([
        'critere_id' => $critereId,
        'user_id' => $userId,
        'points' => $points,
        'verifie' => $verifie,
    ]);
}

/**
 * Annotation avec malus en mémoire.
 */
function malusNote(float $points, ?int $cibleUserId): ProjetAnnotation
{
    return (new ProjetAnnotation)->forceFill(['points_malus' => $points, 'cible_user_id' => $cibleUserId]);
}

test('un critère positif ne compte que s’il est vérifié, avec le pointage complet si aucun point n’est saisi', function () {
    $criteres = collect([critereNote(1, 'positif', 10), critereNote(2, 'positif', 5)]);
    $corrections = collect([
        correctionNote(1, null, null, verifie: true),
        correctionNote(2, null, 4, verifie: false),
    ]);

    expect(app(CalculNotesService::class)->points($criteres, $corrections, collect(), 7))->toBe(10.0);
});

test('la correction individuelle prime sur celle du groupe', function () {
    $criteres = collect([critereNote(1, 'positif', 10)]);
    $corrections = collect([
        correctionNote(1, null, 10),
        correctionNote(1, 7, 3),
    ]);

    $service = app(CalculNotesService::class);

    expect($service->points($criteres, $corrections, collect(), 7))->toBe(3.0)
        ->and($service->points($criteres, $corrections, collect(), 8))->toBe(10.0);
});

test('un critère négatif retire le pointage complet si aucun point n’est saisi', function () {
    $criteres = collect([critereNote(1, 'positif', 10), critereNote(2, 'negatif', 2)]);
    $corrections = collect([correctionNote(1, null, null), correctionNote(2, null, null)]);

    expect(app(CalculNotesService::class)->points($criteres, $corrections, collect(), 7))->toBe(8.0);
});

test('les malus visant tout le groupe ou l’étudiant sont déduits, pas ceux d’un autre', function () {
    $criteres = collect([critereNote(1, 'positif', 10)]);
    $corrections = collect([correctionNote(1, null, null)]);
    $annotations = collect([malusNote(1, null), malusNote(2, 7), malusNote(4, 99)]);

    expect(app(CalculNotesService::class)->points($criteres, $corrections, $annotations, 7))->toBe(7.0);
});

test('le pourcentage se calcule sur le maximum positif et vaut null sans critère positif', function () {
    $service = app(CalculNotesService::class);
    $corrections = collect([correctionNote(1, null, 3)]);

    expect($service->pourcentage(collect([critereNote(1, 'positif', 4)]), $corrections, collect(), 7))->toBe(75.0)
        ->and($service->pourcentage(collect([critereNote(1, 'negatif', 4)]), $corrections, collect(), 7))->toBeNull();
});

test('un critère sans correction ne rapporte ni ne retire de points', function () {
    $criteres = collect([critereNote(1, 'positif', 10), critereNote(2, 'negatif', 3), critereNote(3, 'positif', 5)]);
    $corrections = collect([correctionNote(3, null, 4)]);

    expect(app(CalculNotesService::class)->points($criteres, $corrections, collect(), 7))->toBe(4.0);
});
