<?php

use App\Models\Classe;
use App\Models\Cours;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function creerCoursAvecEnseignant(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = Cours::create([
        'nom_cours' => 'Histoire du Quebec',
        'description' => 'Cours test',
        'code' => '330-TEST',
        'groupe' => 'A',
        'enseignant_id' => $enseignant->id,
    ]);

    return compact('enseignant', 'cours');
}

function creerClassePourCours(Cours $cours, string $code): Classe
{
    return Classe::forceCreate([
        'id' => $cours->id,
        'cours_id' => $cours->id,
        'numero' => '00001',
        'code' => $cours->code,
        'nom' => "Classe {$code}",
    ]);
}

test('store cree une section pour le cours', function () {
    ['enseignant' => $enseignant, 'cours' => $cours] = creerCoursAvecEnseignant();

    $this->actingAs($enseignant)
        ->post("/cours/{$cours->id}/classes", [
            'numero' => '00001',
            'nom' => 'Classe 00001',
            'jour_semaine' => 'Lundi',
            'plage_horaire' => '08:30 - 11:30',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('classes', [
        'cours_id' => $cours->id,
        'numero' => '00001',
        'code' => $cours->code,
        'nom' => 'Classe 00001',
    ]);
});

test('store refuse un etudiant non autorise', function () {
    ['cours' => $cours] = creerCoursAvecEnseignant();
    $etudiant = User::factory()->create(['role' => 'etudiant']);

    $this->actingAs($etudiant)
        ->post("/cours/{$cours->id}/classes", [])
        ->assertRedirect();
});

test('show retourne 404 quand la classe ne correspond pas au cours', function () {
    ['enseignant' => $enseignant, 'cours' => $cours] = creerCoursAvecEnseignant();
    ['cours' => $autreCours] = creerCoursAvecEnseignant();

    $classe = creerClassePourCours($cours, '402');

    $this->actingAs($enseignant)
        ->get("/cours/{$autreCours->id}/classes/{$classe->id}")
        ->assertNotFound();
});

test('show est accessible a un etudiant inscrit dans la section', function () {
    ['cours' => $cours] = creerCoursAvecEnseignant();
    $etudiant = User::factory()->create(['role' => 'etudiant']);

    $classe = creerClassePourCours($cours, '403');
    $classe->etudiants()->attach($etudiant->id);

    $this->actingAs($etudiant)
        ->get("/cours/{$cours->id}/classes/{$classe->id}")
        ->assertOk();
});

test('destroy supprime la section pour l enseignant du cours', function () {
    ['enseignant' => $enseignant, 'cours' => $cours] = creerCoursAvecEnseignant();
    $classe = creerClassePourCours($cours, '404');

    $this->actingAs($enseignant)
        ->delete("/cours/{$cours->id}/classes/{$classe->id}")
        ->assertRedirect();

    $this->assertDatabaseMissing('classes', ['id' => $classe->id]);
});
