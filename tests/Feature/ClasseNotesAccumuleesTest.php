<?php

use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetCritereCorrection;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use App\Models\TypeProjetCritere;
use App\Models\User;

/**
 * Crée un type de projet sommatif avec un critère positif de 10 points.
 *
 * @return array{0: TypeProjet, 1: TypeProjetCritere}
 */
function creerTypeProjetPondere(Cours $cours, string $nom, ?float $ponderation, bool $estSommatif = true): array
{
    $typeProjet = TypeProjet::create([
        'enseignant_id' => $cours->enseignant_id,
        'cours_id' => $cours->id,
        'nom' => $nom,
        'ponderation' => $ponderation,
        'is_sommatif' => $estSommatif,
    ]);

    $critere = TypeProjetCritere::create([
        'type_projet_id' => $typeProjet->id,
        'type' => 'positif',
        'contenu_type' => 'texte',
        'pointage' => 10,
        'visible' => true,
        'ordre' => 1,
    ]);

    return [$typeProjet, $critere];
}

test("un étudiant inscrit n'a pas accès aux aperçus de notes de sa classe", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = Cours::create(['nom_cours' => 'Histoire', 'code' => '330-NA', 'groupe' => '01', 'enseignant_id' => $enseignant->id]);
    $classe = Classe::create(['cours_id' => $cours->id]);
    $etudiant = User::factory()->create(['role' => 'etudiant']);
    $classe->etudiants()->attach($etudiant->id);
    [$typeProjet] = creerTypeProjetPondere($cours, 'TP', 50);

    // Le middleware role:enseignant,admin redirige avant la policy, qui reste une seconde barrière
    $this->actingAs($etudiant)->get("/cours/{$cours->id}/classes/{$classe->id}/apercu-notes-accumulees")->assertRedirect();
    $this->actingAs($etudiant)->get("/cours/{$cours->id}/classes/{$classe->id}/types-projets/{$typeProjet->id}/apercu-notes")->assertRedirect();

    expect($etudiant->can('view', $classe))->toBeTrue()
        ->and($etudiant->can('voirNotes', $classe))->toBeFalse();
});

test("un admin a accès aux aperçus de notes d'une classe", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = Cours::create(['nom_cours' => 'Histoire', 'code' => '330-NA', 'groupe' => '01', 'enseignant_id' => $enseignant->id]);
    $classe = Classe::create(['cours_id' => $cours->id]);
    [$typeProjet] = creerTypeProjetPondere($cours, 'TP', 50);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->get("/cours/{$cours->id}/classes/{$classe->id}/apercu-notes-accumulees")->assertOk();
    $this->actingAs($admin)->get("/cours/{$cours->id}/classes/{$classe->id}/types-projets/{$typeProjet->id}/apercu-notes")->assertOk();
});

test("les aperçus de notes répondent 404 si la classe n'appartient pas au cours", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = Cours::create(['nom_cours' => 'Histoire', 'code' => '330-NA', 'groupe' => '01', 'enseignant_id' => $enseignant->id]);
    $autreCours = Cours::create(['nom_cours' => 'Géo', 'code' => '320-NA', 'groupe' => '01', 'enseignant_id' => $enseignant->id]);
    $classe = Classe::create(['cours_id' => $autreCours->id]);

    $this->actingAs($enseignant)->get("/cours/{$cours->id}/classes/{$classe->id}/apercu-notes-accumulees")->assertNotFound();
});

test('les notes accumulées pondèrent chaque type de projet sommatif par étudiant', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = Cours::create(['nom_cours' => 'Histoire', 'code' => '330-NA', 'groupe' => '01', 'enseignant_id' => $enseignant->id]);
    $classe = Classe::create(['cours_id' => $cours->id]);

    $etudiant = User::factory()->create(['role' => 'etudiant', 'prenom' => 'Ana', 'nom' => 'Roy', 'no_da' => '12-345']);
    $sansProjet = User::factory()->create(['role' => 'etudiant', 'prenom' => 'Léo', 'nom' => 'Gay', 'no_da' => '999']);

    $groupe = Groupe::create(['classe_id' => $classe->id, 'created_by' => $etudiant->id]);
    $groupe->membres()->attach($etudiant->id);
    Groupe::create(['classe_id' => $classe->id, 'created_by' => $sansProjet->id])->membres()->attach($sansProjet->id);

    [$tpB, $critereB] = creerTypeProjetPondere($cours, 'B projet', 40);
    [$tpA, $critereA] = creerTypeProjetPondere($cours, 'A projet', 60);
    creerTypeProjetPondere($cours, 'Formatif', 30, false);
    creerTypeProjetPondere($cours, 'Sans pondération', null);

    $projetA = ProjetRecherche::create(['groupe_id' => $groupe->id, 'type_projet_id' => $tpA->id]);
    ProjetCritereCorrection::create(['projet_id' => $projetA->id, 'critere_id' => $critereA->id, 'verifie' => true, 'points' => 5]);
    $projetB = ProjetRecherche::create(['groupe_id' => $groupe->id, 'type_projet_id' => $tpB->id]);
    ProjetCritereCorrection::create(['projet_id' => $projetB->id, 'critere_id' => $critereB->id, 'user_id' => $etudiant->id, 'verifie' => true]);

    $this->actingAs($enseignant)
        ->get("/cours/{$cours->id}/classes/{$classe->id}/apercu-notes-accumulees")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Classes/ApercuNotesAccumulees')
            ->where('cours', ['id' => $cours->id, 'nom_cours' => 'Histoire'])
            ->where('classe.id', $classe->id)
            ->where('typesProjets', [
                ['id' => $tpA->id, 'nom' => 'A projet', 'ponderation' => 60],
                ['id' => $tpB->id, 'nom' => 'B projet', 'ponderation' => 40],
            ])
            ->where('sommePonderations', 100)
            ->has('lignes', 2)
            // A : 5/10 = 50 % × 60 % = 30 ; B : 10/10 = 100 % × 40 % = 40
            ->where('lignes.0', [
                'da' => '12345',
                'prenom' => 'Ana',
                'nom' => 'Roy',
                'notes_par_type' => [$tpA->id => 50, $tpB->id => 100],
                'total' => 70,
            ])
            ->where('lignes.1', [
                'da' => '999',
                'prenom' => 'Léo',
                'nom' => 'Gay',
                'notes_par_type' => [$tpA->id => null, $tpB->id => null],
                'total' => 0,
            ])
        );
});
