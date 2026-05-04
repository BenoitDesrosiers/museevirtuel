<?php

use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\GroupeTache;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use App\Models\TypeProjetSection;
use App\Models\TypeProjetTache;
use App\Models\User;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Crée un scénario : enseignant, cours (type tache), étudiant, classe, groupe, projet.
 *
 * @return array{enseignant: User, etudiant: User, cours: Cours, classeSection: Classe, groupe: Groupe, typeProjet: TypeProjet, projet: ProjetRecherche}
 */
function creerScenarioTaches(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $etudiant = User::factory()->create(['role' => 'etudiant']);

    $cours = Cours::create([
        'nom_cours' => 'Cours CC Tâches',
        'code' => '330-TCH',
        'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);

    $classeSection = Classe::create(['cours_id' => $cours->id]);
    $classeSection->etudiants()->attach($etudiant->id);

    $groupe = Groupe::create(['classe_id' => $classeSection->id, 'created_by' => $etudiant->id]);
    $groupe->membres()->attach($etudiant->id);

    $typeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id,
        'cours_id' => $cours->id,
        'nom' => 'Projet avec tâches',
        'accessible' => true,
    ]);

    TypeProjetSection::create([
        'type_projet_id' => $typeProjet->id,
        'label' => 'Tâches à réaliser',
        'type' => 'tache',
        'ordre' => 1,
    ]);

    $projet = ProjetRecherche::create([
        'groupe_id' => $groupe->id,
        'type_projet_id' => $typeProjet->id,
    ]);

    return compact('enseignant', 'etudiant', 'cours', 'classeSection', 'groupe', 'typeProjet', 'projet');
}

/** URL de base pour la gestion des tâches (enseignant). */
function urlTaches(Cours $cours, TypeProjet $typeProjet): string
{
    return "/cours/{$cours->id}/types-projets/{$typeProjet->id}/taches";
}

/** URL de toggle/assigner (étudiant). */
function urlGroupeTache(Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, TypeProjetTache $tache, string $action): string
{
    return "/cours/{$cours->id}/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/taches/{$tache->id}/{$action}";
}

// ─── store() — Enseignant ─────────────────────────────────────────────────────

test("l'enseignant peut ajouter une tâche à un TypeProjet", function () {
    $s = creerScenarioTaches();

    $this->actingAs($s['enseignant'])
        ->post(urlTaches($s['cours'], $s['typeProjet']), [
            'titre' => 'Préparer les questions',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('type_projet_taches', [
        'type_projet_id' => $s['typeProjet']->id,
        'titre' => 'Préparer les questions',
    ]);
});

test("l'ordre est automatiquement incrémenté lors de l'ajout de tâches", function () {
    $s = creerScenarioTaches();

    $this->actingAs($s['enseignant'])
        ->post(urlTaches($s['cours'], $s['typeProjet']), ['titre' => 'Tâche A']);

    $this->actingAs($s['enseignant'])
        ->post(urlTaches($s['cours'], $s['typeProjet']), ['titre' => 'Tâche B']);

    $taches = TypeProjetTache::where('type_projet_id', $s['typeProjet']->id)->orderBy('ordre')->get();
    expect($taches[0]->titre)->toBe('Tâche A');
    expect($taches[0]->ordre)->toBe(1);
    expect($taches[1]->titre)->toBe('Tâche B');
    expect($taches[1]->ordre)->toBe(2);
});

test("un enseignant d'un autre cours ne peut pas ajouter de tâches", function () {
    $s = creerScenarioTaches();
    $autreEnseignant = User::factory()->create(['role' => 'enseignant']);

    $this->actingAs($autreEnseignant)
        ->post(urlTaches($s['cours'], $s['typeProjet']), ['titre' => 'Intrusion'])
        ->assertForbidden();
});

// ─── update() — Enseignant ────────────────────────────────────────────────────

test("l'enseignant peut modifier une tâche", function () {
    $s = creerScenarioTaches();

    $tache = TypeProjetTache::create([
        'type_projet_id' => $s['typeProjet']->id,
        'titre' => 'Ancienne tâche',
        'ordre' => 1,
    ]);

    $this->actingAs($s['enseignant'])
        ->put(urlTaches($s['cours'], $s['typeProjet'])."/{$tache->id}", [
            'titre' => 'Tâche modifiée',
            'description' => 'Une description utile',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('type_projet_taches', [
        'id' => $tache->id,
        'titre' => 'Tâche modifiée',
        'description' => 'Une description utile',
    ]);
});

// ─── destroy() + renumerotation ───────────────────────────────────────────────

test("l'enseignant peut supprimer une tâche", function () {
    $s = creerScenarioTaches();

    $tache = TypeProjetTache::create([
        'type_projet_id' => $s['typeProjet']->id,
        'titre' => 'À supprimer',
        'ordre' => 1,
    ]);

    $this->actingAs($s['enseignant'])
        ->delete(urlTaches($s['cours'], $s['typeProjet'])."/{$tache->id}")
        ->assertRedirect();

    $this->assertDatabaseMissing('type_projet_taches', ['id' => $tache->id]);
});

test('la suppression renumérote les tâches restantes', function () {
    $s = creerScenarioTaches();

    $t1 = TypeProjetTache::create(['type_projet_id' => $s['typeProjet']->id, 'titre' => 'T1', 'ordre' => 1]);
    $t2 = TypeProjetTache::create(['type_projet_id' => $s['typeProjet']->id, 'titre' => 'T2', 'ordre' => 2]);
    $t3 = TypeProjetTache::create(['type_projet_id' => $s['typeProjet']->id, 'titre' => 'T3', 'ordre' => 3]);

    $this->actingAs($s['enseignant'])
        ->delete(urlTaches($s['cours'], $s['typeProjet'])."/{$t1->id}");

    expect($t2->fresh()->ordre)->toBe(1);
    expect($t3->fresh()->ordre)->toBe(2);
});

// ─── reorder() — Enseignant ───────────────────────────────────────────────────

test("l'enseignant peut réordonner les tâches", function () {
    $s = creerScenarioTaches();

    $t1 = TypeProjetTache::create(['type_projet_id' => $s['typeProjet']->id, 'titre' => 'T1', 'ordre' => 1]);
    $t2 = TypeProjetTache::create(['type_projet_id' => $s['typeProjet']->id, 'titre' => 'T2', 'ordre' => 2]);

    $this->actingAs($s['enseignant'])
        ->patch(urlTaches($s['cours'], $s['typeProjet']).'/reorder', [
            'ordre' => [$t2->id, $t1->id],
        ])
        ->assertRedirect();

    expect($t2->fresh()->ordre)->toBe(1);
    expect($t1->fresh()->ordre)->toBe(2);
});

// ─── toggleCompleted() + assigner() — Étudiant ───────────────────────────────

test('un étudiant membre peut marquer une tâche comme complétée', function () {
    $s = creerScenarioTaches();

    $tache = TypeProjetTache::create([
        'type_projet_id' => $s['typeProjet']->id,
        'titre' => 'Tâche à faire',
        'ordre' => 1,
    ]);

    $this->actingAs($s['etudiant'])
        ->patch(urlGroupeTache($s['cours'], $s['classeSection'], $s['groupe'], $s['typeProjet'], $tache, 'toggle'))
        ->assertRedirect();

    $groupeTache = GroupeTache::where([
        'tache_id' => $tache->id,
        'groupe_id' => $s['groupe']->id,
    ])->first();

    expect($groupeTache)->not->toBeNull();
    expect($groupeTache->completed_at)->not->toBeNull();
});

test('un second toggle sur une tâche complétée la remet à non-complétée', function () {
    $s = creerScenarioTaches();

    $tache = TypeProjetTache::create([
        'type_projet_id' => $s['typeProjet']->id,
        'titre' => 'Tâche',
        'ordre' => 1,
    ]);

    // Premier toggle → complété
    $this->actingAs($s['etudiant'])
        ->patch(urlGroupeTache($s['cours'], $s['classeSection'], $s['groupe'], $s['typeProjet'], $tache, 'toggle'));

    // Second toggle → non-complété
    $this->actingAs($s['etudiant'])
        ->patch(urlGroupeTache($s['cours'], $s['classeSection'], $s['groupe'], $s['typeProjet'], $tache, 'toggle'));

    $groupeTache = GroupeTache::where([
        'tache_id' => $tache->id,
        'groupe_id' => $s['groupe']->id,
    ])->first();

    expect($groupeTache->completed_at)->toBeNull();
});

test('un étudiant membre peut assigner un membre à une tâche', function () {
    $s = creerScenarioTaches();

    $tache = TypeProjetTache::create([
        'type_projet_id' => $s['typeProjet']->id,
        'titre' => 'Tâche assignable',
        'ordre' => 1,
    ]);

    $this->actingAs($s['etudiant'])
        ->patch(urlGroupeTache($s['cours'], $s['classeSection'], $s['groupe'], $s['typeProjet'], $tache, 'assigner'), [
            'assigne_a' => $s['etudiant']->id,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('groupe_taches', [
        'tache_id' => $tache->id,
        'groupe_id' => $s['groupe']->id,
        'assigne_a' => $s['etudiant']->id,
    ]);
});

test('un étudiant non-membre ne peut pas modifier les tâches du groupe', function () {
    $s = creerScenarioTaches();
    $intrus = User::factory()->create(['role' => 'etudiant']);

    $tache = TypeProjetTache::create([
        'type_projet_id' => $s['typeProjet']->id,
        'titre' => 'Tâche',
        'ordre' => 1,
    ]);

    $this->actingAs($intrus)
        ->patch(urlGroupeTache($s['cours'], $s['classeSection'], $s['groupe'], $s['typeProjet'], $tache, 'toggle'))
        ->assertForbidden();
});
