<?php

use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\TypeProjet;
use App\Models\User;

/**
 * Crée un cours et son enseignant.
 *
 * @return array{enseignant: User, cours: Cours}
 */
function creerCoursPourPolicies(string $code = '330-TPP'): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = Cours::create([
        'nom_cours' => 'Histoire',
        'code' => $code,
        'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);

    return compact('enseignant', 'cours');
}

// ─── TypeProjetPolicy ─────────────────────────────────────────────────────────

test("TypeProjetPolicy : l'enseignant du cours gère un type de projet créé par un admin", function () {
    ['enseignant' => $enseignant, 'cours' => $cours] = creerCoursPourPolicies();
    $admin = User::factory()->create(['role' => 'admin']);

    // TypeProjet::enseignant_id est le créateur (ici l'admin), pas l'enseignant du cours
    $typeProjet = TypeProjet::create(['enseignant_id' => $admin->id, 'cours_id' => $cours->id, 'nom' => 'Projet']);

    expect($enseignant->can('update', $typeProjet))->toBeTrue()
        ->and($enseignant->can('delete', $typeProjet))->toBeTrue()
        ->and($admin->can('update', $typeProjet))->toBeTrue();

    $this->actingAs($enseignant)
        ->get("/cours/{$cours->id}/types-projets/{$typeProjet->id}/edit")
        ->assertOk();
});

test("TypeProjetPolicy : l'enseignant d'un autre cours ne gère pas le type de projet, même s'il l'a créé", function () {
    ['cours' => $cours] = creerCoursPourPolicies();
    ['enseignant' => $autre] = creerCoursPourPolicies('330-AUT');
    $typeProjet = TypeProjet::create(['enseignant_id' => $autre->id, 'cours_id' => $cours->id, 'nom' => 'Projet']);

    expect($autre->can('update', $typeProjet))->toBeFalse();

    $this->actingAs($autre)
        ->get("/cours/{$cours->id}/types-projets/{$typeProjet->id}/edit")
        ->assertForbidden();
});

test("TypeProjetPolicy : un type de projet d'un autre cours dans l'URL renvoie 404", function () {
    ['enseignant' => $enseignant, 'cours' => $cours] = creerCoursPourPolicies();
    ['cours' => $autreCours] = creerCoursPourPolicies('330-AUT');
    $typeProjet = TypeProjet::create(['enseignant_id' => $enseignant->id, 'cours_id' => $autreCours->id, 'nom' => 'Projet']);

    $this->actingAs($enseignant)
        ->get("/cours/{$cours->id}/types-projets/{$typeProjet->id}/edit")
        ->assertNotFound();
});

// ─── GroupePolicy::create ─────────────────────────────────────────────────────

test('GroupePolicy::create : seul un étudiant inscrit dans la classe peut y créer un groupe', function () {
    ['cours' => $cours] = creerCoursPourPolicies();
    $classe = Classe::create(['cours_id' => $cours->id]);
    $inscrit = User::factory()->create(['role' => 'etudiant']);
    $classe->etudiants()->attach($inscrit->id);
    $nonInscrit = User::factory()->create(['role' => 'etudiant']);

    expect($inscrit->can('create', [Groupe::class, $classe]))->toBeTrue()
        ->and($nonInscrit->can('create', [Groupe::class, $classe]))->toBeFalse();
});

// ─── UserPolicy ───────────────────────────────────────────────────────────────

test('UserPolicy::delete : on ne peut supprimer que son propre compte', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $autreAdmin = User::factory()->create(['role' => 'admin']);

    expect($admin->can('delete', $admin))->toBeTrue()
        ->and($admin->can('delete', $autreAdmin))->toBeFalse();
});

test('UserPolicy::gererEnseignant : un admin ne gère que des comptes enseignants', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $autreAdmin = User::factory()->create(['role' => 'admin']);
    $etudiant = User::factory()->create(['role' => 'etudiant']);

    expect($admin->can('gererEnseignant', $enseignant))->toBeTrue()
        ->and($admin->can('gererEnseignant', $autreAdmin))->toBeFalse()
        ->and($admin->can('gererEnseignant', $etudiant))->toBeFalse()
        ->and($enseignant->can('gererEnseignant', $enseignant))->toBeFalse();

    $this->actingAs($admin)
        ->delete("/administration/enseignants/{$autreAdmin->id}")
        ->assertForbidden();

    expect(User::find($autreAdmin->id))->not->toBeNull();
});
