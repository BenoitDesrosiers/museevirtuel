<?php

use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetCommentaire;
use App\Models\ProjetDeveloppement;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use App\Models\User;

// ─── Helper ───────────────────────────────────────────────────────────────────

/**
 * Crée un contexte de test avec un enseignant, une section de cours, un groupe,
 * un étudiant membre et un projet.
 *
 * @return array{enseignant: User, cours: Cours, classeSection: Classe, classe: Groupe, etudiant: User, typeProjet: TypeProjet, projet: ProjetRecherche}
 */
function creerContexteProjet(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    $cours = Cours::create([
        'nom_cours' => 'Histoire du Québec',
        'description' => 'Test',
        'code' => '330-2E1',
        'groupe' => '0001',
        'enseignant_id' => $enseignant->id,
    ]);

    $etudiant = User::factory()->create(['role' => 'etudiant']);

    $classeSection = Classe::create(['cours_id' => $cours->id]);
    $classeSection->etudiants()->attach($etudiant->id);

    $classe = Groupe::create([
        'classe_id' => $classeSection->id,
        'created_by' => $etudiant->id,
    ]);
    $classe->membres()->attach($etudiant->id);

    $typeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id,
        'nom' => 'Projet test',
        'accessible' => true,
    ]);

    $projet = ProjetRecherche::create([
        'groupe_id' => $classe->id,
        'type_projet_id' => $typeProjet->id,
    ]);

    return compact('enseignant', 'cours', 'classeSection', 'classe', 'etudiant', 'typeProjet', 'projet');
}

// ─── Commentaires — autorisation ──────────────────────────────────────────────

test("l'enseignant peut créer un commentaire sur un champ du projet", function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classeSection' => $cs, 'classe' => $classe, 'typeProjet' => $typeProjet] = creerContexteProjet();

    $this->actingAs($enseignant)
        ->putJson("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/commentaires", [
            'champ' => 'introduction_amener',
            'contenu' => 'Pensez à contextualiser davantage.',
        ])
        ->assertOk()
        ->assertJsonStructure(['message', 'id', 'contenu']);

    $this->assertDatabaseHas('projet_commentaires', [
        'champ' => 'introduction_amener',
        'contenu' => 'Pensez à contextualiser davantage.',
    ]);
});

test('un étudiant ne peut pas créer un commentaire', function () {
    ['etudiant' => $etudiant, 'cours' => $cours, 'classeSection' => $cs, 'classe' => $classe, 'typeProjet' => $typeProjet] = creerContexteProjet();

    $this->actingAs($etudiant)
        ->putJson("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/commentaires", [
            'champ' => 'introduction_amener',
            'contenu' => 'Tentative non autorisée.',
        ])
        ->assertForbidden();
});

// ─── Commentaires — upsert ────────────────────────────────────────────────────

test('un deuxième PUT sur le même champ met à jour le commentaire sans créer de doublon', function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classeSection' => $cs, 'classe' => $classe, 'typeProjet' => $typeProjet, 'projet' => $projet] = creerContexteProjet();

    $dev = ProjetDeveloppement::create([
        'projet_id' => $projet->id,
        'ordre' => 1,
        'titre' => 'Paragraphe test',
        'contenu' => '<p>Contenu.</p>',
    ]);

    $champ = "developpement_{$dev->id}";
    $url = "/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/commentaires";

    $this->actingAs($enseignant)->putJson($url, [
        'champ' => $champ,
        'contenu' => 'Premier commentaire.',
    ]);

    $this->actingAs($enseignant)->putJson($url, [
        'champ' => $champ,
        'contenu' => 'Commentaire mis à jour.',
    ])->assertOk();

    expect(ProjetCommentaire::where('projet_id', $projet->id)->where('champ', $champ)->count())->toBe(1);
    $this->assertDatabaseHas('projet_commentaires', ['contenu' => 'Commentaire mis à jour.']);
});

test("l'enseignant peut supprimer un commentaire", function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classeSection' => $cs, 'classe' => $classe, 'typeProjet' => $typeProjet, 'projet' => $projet] = creerContexteProjet();

    $commentaire = ProjetCommentaire::create([
        'projet_id' => $projet->id,
        'champ' => 'introduction_poser',
        'contenu' => 'À retravailler.',
        'created_by' => $enseignant->id,
    ]);

    $this->actingAs($enseignant)
        ->deleteJson("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/commentaires/{$commentaire->id}")
        ->assertOk()
        ->assertJson(['message' => 'deleted']);

    $this->assertDatabaseMissing('projet_commentaires', ['id' => $commentaire->id]);
});

// ─── Commentaires — validation ────────────────────────────────────────────────

test('un champ non autorisé retourne une erreur de validation', function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classeSection' => $cs, 'classe' => $classe, 'typeProjet' => $typeProjet] = creerContexteProjet();

    $this->actingAs($enseignant)
        ->putJson("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/commentaires", [
            'champ' => 'champ_inexistant',
            'contenu' => 'Test.',
        ])
        ->assertUnprocessable();
});

test('le contenu du commentaire est obligatoire', function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classeSection' => $cs, 'classe' => $classe, 'typeProjet' => $typeProjet] = creerContexteProjet();

    $this->actingAs($enseignant)
        ->putJson("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/commentaires", [
            'champ' => 'introduction_amener',
            'contenu' => '',
        ])
        ->assertUnprocessable();
});
