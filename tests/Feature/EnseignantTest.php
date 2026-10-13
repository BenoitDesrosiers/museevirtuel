<?php

use App\Models\Classe;
use App\Models\Cours;
use App\Models\User;

test("l'index enseignant retourne le nombre d'étudiants inscrits par cours", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $etudiant = User::factory()->create(['role' => 'etudiant']);
    $autreEtudiant = User::factory()->create(['role' => 'etudiant']);

    $cours = Cours::create([
        'nom_cours' => 'Cours de test',
        'code' => '330-TST',
        'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);

    $premiereClasse = Classe::create(['cours_id' => $cours->id]);
    $deuxiemeClasse = Classe::create(['cours_id' => $cours->id]);

    $premiereClasse->etudiants()->attach($etudiant->id);
    $deuxiemeClasse->etudiants()->attach([$etudiant->id, $autreEtudiant->id]);

    $coursRetournes = collect(
        $this->actingAs($enseignant)
            ->get('/enseignant')
            ->assertOk()
            ->inertiaProps('cours'),
    );

    expect($coursRetournes->firstWhere('id', $cours->id)['etudiants_count'])->toBe(2);
});
