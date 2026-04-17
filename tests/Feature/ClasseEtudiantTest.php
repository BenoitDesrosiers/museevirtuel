<?php

use App\Models\Classe;
use App\Models\Cours;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

function creerContexteClasseEtudiant(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = Cours::create([
        'nom_cours' => 'Sciences humaines',
        'description' => 'Cours test',
        'code' => '387-TEST',
        'groupe' => 'A',
        'enseignant_id' => $enseignant->id,
    ]);

    $classe = Classe::forceCreate([
        'cours_id' => $cours->id,
        'numero' => '00001',
        'code' => $cours->code,
        'nom' => 'Classe 00001',
        'jour_semaine' => 'Lundi',
        'plage_horaire' => '08:30 - 11:30',
    ]);

    return compact('enseignant', 'cours', 'classe');
}

test('store ajoute un etudiant a une section', function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classe' => $classe] = creerContexteClasseEtudiant();

    $this->actingAs($enseignant)
        ->post("/cours/{$cours->id}/classes/{$classe->id}/etudiants", [
            'prenom' => 'Alice',
            'nom' => 'Tremblay',
            'email' => 'alice.tremblay@example.com',
            'no_da' => '1234567',
            'statut_cours' => 'actif',
        ])
        ->assertRedirect();

    $etudiant = User::where('no_da', '1234567')->first();
    expect($etudiant)->not->toBeNull();

    $this->assertDatabaseHas('classe_etudiant', [
        'classe_id' => $classe->id,
        'user_id' => $etudiant->id,
        'statut_cours' => 'actif',
    ]);
});

test('update modifie etudiant et statut dans la section', function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classe' => $classe] = creerContexteClasseEtudiant();
    $etudiant = User::factory()->create([
        'role' => 'etudiant',
        'prenom' => 'Bob',
        'nom' => 'Lavoie',
        'email' => 'bob@example.com',
        'no_da' => '7654321',
    ]);
    $classe->etudiants()->attach($etudiant->id, ['statut_cours' => 'ancien']);

    $this->actingAs($enseignant)
        ->put("/cours/{$cours->id}/classes/{$classe->id}/etudiants/{$etudiant->id}", [
            'prenom' => 'Robert',
            'nom' => 'Lavoie',
            'email' => 'robert@example.com',
            'no_da' => '7654321',
            'statut_cours' => 'actif',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('users', [
        'id' => $etudiant->id,
        'prenom' => 'Robert',
        'email' => 'robert@example.com',
    ]);
    $this->assertDatabaseHas('classe_etudiant', [
        'classe_id' => $classe->id,
        'user_id' => $etudiant->id,
        'statut_cours' => 'actif',
    ]);
});

test('destroy retire etudiant de la section', function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classe' => $classe] = creerContexteClasseEtudiant();
    $etudiant = User::factory()->create(['role' => 'etudiant']);
    $classe->etudiants()->attach($etudiant->id);

    $this->actingAs($enseignant)
        ->delete("/cours/{$cours->id}/classes/{$classe->id}/etudiants/{$etudiant->id}")
        ->assertRedirect();

    $this->assertDatabaseMissing('classe_etudiant', [
        'classe_id' => $classe->id,
        'user_id' => $etudiant->id,
    ]);
});

test('import ajoute des etudiants depuis csv', function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classe' => $classe] = creerContexteClasseEtudiant();

    $csv = "no_da;nom;prenom;statut_cours\n123001;Bouchard;Lea;actif\n123002;Gagne;Noah;inactif\n";
    $file = UploadedFile::fake()->createWithContent('etudiants.csv', $csv);

    $this->actingAs($enseignant)
        ->post("/cours/{$cours->id}/classes/{$classe->id}/etudiants/import", [
            'csv' => $file,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('users', ['no_da' => '123001']);
    $this->assertDatabaseHas('users', ['no_da' => '123002']);
});

test('store retourne 403 pour un utilisateur non autorise', function () {
    ['cours' => $cours, 'classe' => $classe] = creerContexteClasseEtudiant();
    $etudiant = User::factory()->create(['role' => 'etudiant']);

    $this->actingAs($etudiant)
        ->post("/cours/{$cours->id}/classes/{$classe->id}/etudiants", [
            'prenom' => 'Alice',
            'nom' => 'Tremblay',
            'email' => 'alice.tremblay@example.com',
            'no_da' => '1234567',
            'statut_cours' => 'actif',
        ])
        ->assertRedirect();

    $this->assertDatabaseMissing('users', ['no_da' => '1234567']);
});
