<?php

use App\Models\Classe;
use App\Models\Cours;
use App\Models\CoursReference;
use App\Models\User;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Crée un enseignant + cours minimal pour les tests de références.
 *
 * @return array{enseignant: User, cours: Cours}
 */
function creerScenarioRef(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    $cours = Cours::create([
        'nom_cours' => 'Histoire du Québec',
        'code' => '330-REF',
        'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);

    return compact('enseignant', 'cours');
}

/** URL de base pour les références du cours. */
function urlRefs(Cours $cours): string
{
    return "/cours/{$cours->id}/references";
}

// ─── store() ──────────────────────────────────────────────────────────────────

test("l'enseignant peut ajouter une référence avec nom et url", function () {
    ['enseignant' => $enseignant, 'cours' => $cours] = creerScenarioRef();

    $this->actingAs($enseignant)
        ->post(urlRefs($cours), [
            'nom' => 'Revue d\'histoire de l\'Amérique française',
            'url' => 'https://www.erudit.org/en/journals/haf/',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('cours_references', [
        'cours_id' => $cours->id,
        'nom' => 'Revue d\'histoire de l\'Amérique française',
        'url' => 'https://www.erudit.org/en/journals/haf/',
    ]);
});

test("l'enseignant peut ajouter une référence sans url", function () {
    ['enseignant' => $enseignant, 'cours' => $cours] = creerScenarioRef();

    $this->actingAs($enseignant)
        ->post(urlRefs($cours), ['nom' => 'Revue Historia'])
        ->assertRedirect();

    $this->assertDatabaseHas('cours_references', [
        'cours_id' => $cours->id,
        'nom' => 'Revue Historia',
        'url' => null,
    ]);
});

test("l'ordre est incrémenté automatiquement à l'ajout", function () {
    ['enseignant' => $enseignant, 'cours' => $cours] = creerScenarioRef();

    $this->actingAs($enseignant)->post(urlRefs($cours), ['nom' => 'Référence A']);
    $this->actingAs($enseignant)->post(urlRefs($cours), ['nom' => 'Référence B']);

    $refs = $cours->references()->orderBy('ordre')->pluck('ordre')->all();

    expect($refs)->toBe([1, 2]);
});

test('store rejette un nom vide', function () {
    ['enseignant' => $enseignant, 'cours' => $cours] = creerScenarioRef();

    $this->actingAs($enseignant)
        ->post(urlRefs($cours), ['nom' => '', 'url' => 'https://example.com'])
        ->assertSessionHasErrors('nom');
});

test('store rejette une url invalide', function () {
    ['enseignant' => $enseignant, 'cours' => $cours] = creerScenarioRef();

    $this->actingAs($enseignant)
        ->post(urlRefs($cours), ['nom' => 'Revue', 'url' => 'pas-une-url'])
        ->assertSessionHasErrors('url');
});

test('un autre enseignant ne peut pas ajouter de références', function () {
    ['cours' => $cours] = creerScenarioRef();
    $intrus = User::factory()->create(['role' => 'enseignant']);

    $this->actingAs($intrus)
        ->post(urlRefs($cours), ['nom' => 'Référence intrus'])
        ->assertForbidden();
});

// ─── update() ─────────────────────────────────────────────────────────────────

test("l'enseignant peut modifier le nom et l'url d'une référence", function () {
    ['enseignant' => $enseignant, 'cours' => $cours] = creerScenarioRef();

    $ref = CoursReference::create([
        'cours_id' => $cours->id, 'nom' => 'Ancien nom', 'url' => null, 'ordre' => 1,
    ]);

    $this->actingAs($enseignant)
        ->put(urlRefs($cours)."/{$ref->id}", [
            'nom' => 'Nouveau nom',
            'url' => 'https://www.lhistoire.fr/',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('cours_references', [
        'id' => $ref->id,
        'nom' => 'Nouveau nom',
        'url' => 'https://www.lhistoire.fr/',
    ]);
});

test('update retourne 404 si la référence appartient à un autre cours', function () {
    ['enseignant' => $enseignant, 'cours' => $cours] = creerScenarioRef();

    $autreCours = Cours::create([
        'nom_cours' => 'Autre cours', 'code' => '330-XX', 'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);

    $refAutre = CoursReference::create([
        'cours_id' => $autreCours->id, 'nom' => 'Référence autre cours', 'ordre' => 1,
    ]);

    $this->actingAs($enseignant)
        ->put(urlRefs($cours)."/{$refAutre->id}", ['nom' => 'Tentative'])
        ->assertNotFound();
});

// ─── destroy() ────────────────────────────────────────────────────────────────

test("l'enseignant peut supprimer une référence", function () {
    ['enseignant' => $enseignant, 'cours' => $cours] = creerScenarioRef();

    $ref = CoursReference::create([
        'cours_id' => $cours->id, 'nom' => 'À supprimer', 'ordre' => 1,
    ]);

    $this->actingAs($enseignant)
        ->delete(urlRefs($cours)."/{$ref->id}")
        ->assertRedirect();

    $this->assertDatabaseMissing('cours_references', ['id' => $ref->id]);
});

test('les ordres sont renumérotés après une suppression', function () {
    ['enseignant' => $enseignant, 'cours' => $cours] = creerScenarioRef();

    $r1 = CoursReference::create(['cours_id' => $cours->id, 'nom' => 'A', 'ordre' => 1]);
    $r2 = CoursReference::create(['cours_id' => $cours->id, 'nom' => 'B', 'ordre' => 2]);
    $r3 = CoursReference::create(['cours_id' => $cours->id, 'nom' => 'C', 'ordre' => 3]);

    // Supprimer la référence du milieu
    $this->actingAs($enseignant)->delete(urlRefs($cours)."/{$r2->id}");

    $ordres = $cours->references()->orderBy('ordre')->pluck('ordre')->all();

    // Pas de trou dans la numérotation
    expect($ordres)->toBe([1, 2]);
});

// ─── reorder() ────────────────────────────────────────────────────────────────

test("l'enseignant peut réordonner les références", function () {
    ['enseignant' => $enseignant, 'cours' => $cours] = creerScenarioRef();

    $r1 = CoursReference::create(['cours_id' => $cours->id, 'nom' => 'A', 'ordre' => 1]);
    $r2 = CoursReference::create(['cours_id' => $cours->id, 'nom' => 'B', 'ordre' => 2]);
    $r3 = CoursReference::create(['cours_id' => $cours->id, 'nom' => 'C', 'ordre' => 3]);

    // Inverser : C, A, B
    $this->actingAs($enseignant)
        ->patch(urlRefs($cours).'/reorder', ['ordre' => [$r3->id, $r1->id, $r2->id]])
        ->assertRedirect();

    expect($r3->fresh()->ordre)->toBe(1)
        ->and($r1->fresh()->ordre)->toBe(2)
        ->and($r2->fresh()->ordre)->toBe(3);
});

// ─── Accès étudiant (lecture seule via Classes/Show) ──────────────────────────

test('un étudiant inscrit voit les références dans la page de sa classe', function () {
    ['enseignant' => $enseignant, 'cours' => $cours] = creerScenarioRef();
    $etudiant = User::factory()->create(['role' => 'etudiant']);

    $classe = Classe::create(['cours_id' => $cours->id]);
    $classe->etudiants()->attach($etudiant->id);

    CoursReference::create(['cours_id' => $cours->id, 'nom' => 'Revue Historia', 'ordre' => 1]);

    $this->actingAs($etudiant)
        ->get("/cours/{$cours->id}/classes/{$classe->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('references', 1)
            ->where('references.0.nom', 'Revue Historia')
        );
});

test('un étudiant non inscrit ne peut pas accéder à la page de classe', function () {
    ['cours' => $cours] = creerScenarioRef();
    $intrus = User::factory()->create(['role' => 'etudiant']);

    $classe = Classe::create(['cours_id' => $cours->id]);

    $this->actingAs($intrus)
        ->get("/cours/{$cours->id}/classes/{$classe->id}")
        ->assertForbidden();
});

test('un étudiant ne peut pas ajouter de référence même si inscrit', function () {
    ['cours' => $cours] = creerScenarioRef();
    $etudiant = User::factory()->create(['role' => 'etudiant']);

    $classe = Classe::create(['cours_id' => $cours->id]);
    $classe->etudiants()->attach($etudiant->id);

    // La route store est sous role:enseignant,admin — le middleware redirige l'étudiant
    $this->actingAs($etudiant)
        ->post(urlRefs($cours), ['nom' => 'Tentative étudiant'])
        ->assertRedirect();

    $this->assertDatabaseMissing('cours_references', ['cours_id' => $cours->id]);
});
