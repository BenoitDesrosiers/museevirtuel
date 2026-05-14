<?php

use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetCommentaire;
use App\Models\ProjetDeveloppement;
use App\Models\ProjetNote;
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

// ─── Notes — autorisation ─────────────────────────────────────────────────────

test("l'enseignant peut sauvegarder une note par étudiant", function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classeSection' => $cs, 'classe' => $classe, 'etudiant' => $etudiant, 'typeProjet' => $typeProjet, 'projet' => $projet] = creerContexteProjet();

    $this->actingAs($enseignant)
        ->putJson("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/notes", [
            'critere' => 'developpement_faits',
            'note' => 4,
            'user_id' => $etudiant->id,
        ])
        ->assertOk()
        ->assertJsonStructure(['message', 'noteFinaleParEtudiant']);

    $this->assertDatabaseHas('projet_notes', [
        'projet_id' => $projet->id,
        'user_id' => $etudiant->id,
        'critere' => 'developpement_faits',
        'note' => 4,
    ]);
});

test('un étudiant ne peut pas sauvegarder une note', function () {
    ['etudiant' => $etudiant, 'cours' => $cours, 'classeSection' => $cs, 'classe' => $classe, 'typeProjet' => $typeProjet] = creerContexteProjet();

    $this->actingAs($etudiant)
        ->putJson("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/notes", [
            'critere' => 'developpement_faits',
            'note' => 4,
            'user_id' => $etudiant->id,
        ])
        ->assertForbidden();
});

test('user_id est obligatoire pour sauvegarder une note', function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classeSection' => $cs, 'classe' => $classe, 'typeProjet' => $typeProjet] = creerContexteProjet();

    $this->actingAs($enseignant)
        ->putJson("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/notes", [
            'critere' => 'ecriture',
            'note' => 3,
            // user_id absent
        ])
        ->assertUnprocessable();
});

// ─── Notes — validation ───────────────────────────────────────────────────────

it('accepte uniquement les notes 0, 2, 3 et 4', function (int $note) {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classeSection' => $cs, 'classe' => $classe, 'etudiant' => $etudiant, 'typeProjet' => $typeProjet] = creerContexteProjet();

    $this->actingAs($enseignant)
        ->putJson("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/notes", [
            'critere' => 'ecriture',
            'note' => $note,
            'user_id' => $etudiant->id,
        ])
        ->assertUnprocessable();
})->with([1, 5, -1, 99]);

test('un critère inexistant retourne une erreur de validation', function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classeSection' => $cs, 'classe' => $classe, 'etudiant' => $etudiant, 'typeProjet' => $typeProjet] = creerContexteProjet();

    $this->actingAs($enseignant)
        ->putJson("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/notes", [
            'critere' => 'critere_inexistant',
            'note' => 4,
            'user_id' => $etudiant->id,
        ])
        ->assertUnprocessable();
});

// ─── Notes — sécurité membre ──────────────────────────────────────────────────

test("l'enseignant ne peut pas noter un étudiant hors du groupe", function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classeSection' => $cs, 'classe' => $classe, 'typeProjet' => $typeProjet] = creerContexteProjet();

    // Étudiant non membre du groupe
    $etudiantHorsGroupe = User::factory()->create(['role' => 'etudiant']);

    $this->actingAs($enseignant)
        ->putJson("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/notes", [
            'critere' => 'ecriture',
            'note' => 3,
            'user_id' => $etudiantHorsGroupe->id,
        ])
        ->assertStatus(422);
});

// ─── Notes — upsert ───────────────────────────────────────────────────────────

test('un deuxième PUT sur le même critère et étudiant met à jour la note sans créer de doublon', function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classeSection' => $cs, 'classe' => $classe, 'etudiant' => $etudiant, 'typeProjet' => $typeProjet, 'projet' => $projet] = creerContexteProjet();

    $url = "/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/notes";

    $this->actingAs($enseignant)->putJson($url, ['critere' => 'ecriture', 'note' => 3, 'user_id' => $etudiant->id]);
    $this->actingAs($enseignant)->putJson($url, ['critere' => 'ecriture', 'note' => 4, 'user_id' => $etudiant->id])->assertOk();

    expect(
        ProjetNote::where('projet_id', $projet->id)
            ->where('critere', 'ecriture')
            ->where('user_id', $etudiant->id)
            ->count()
    )->toBe(1);

    $this->assertDatabaseHas('projet_notes', ['critere' => 'ecriture', 'note' => 4, 'user_id' => $etudiant->id]);
});

// ─── Note finale calculée ─────────────────────────────────────────────────────

test('la note finale par étudiant est correctement calculée sur 100', function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classeSection' => $cs, 'classe' => $classe, 'etudiant' => $etudiant, 'typeProjet' => $typeProjet, 'projet' => $projet] = creerContexteProjet();

    $url = "/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/notes";

    // Tous les critères à "Excellent" (4) pour cet étudiant → note finale = 100
    foreach (array_keys(ProjetNote::CRITERES) as $critere) {
        $this->actingAs($enseignant)->putJson($url, ['critere' => $critere, 'note' => 4, 'user_id' => $etudiant->id]);
    }

    $response = $this->actingAs($enseignant)
        ->putJson($url, ['critere' => 'ecriture', 'note' => 4, 'user_id' => $etudiant->id])
        ->assertOk();

    $noteFinaleParEtudiant = $response->json('noteFinaleParEtudiant');
    expect((float) $noteFinaleParEtudiant[$etudiant->id])->toBe(100.0);
});

test('la note finale vaut 100 si aucune note n\'a été saisie pour cet étudiant', function () {
    ['projet' => $projet, 'etudiant' => $etudiant] = creerContexteProjet();

    // Critères non remplis → 4 (excellent) par défaut → total = 100
    expect(ProjetNote::noteFinale($projet, $etudiant))->toBe(100.0);
});
