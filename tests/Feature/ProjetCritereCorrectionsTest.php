<?php

use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetCritereCorrection;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use App\Models\TypeProjetCritere;
use App\Models\TypeProjetSection;
use App\Models\User;

// ─── Helper ───────────────────────────────────────────────────────────────────

/**
 * Crée un contexte complet pour tester les corrections de critères.
 *
 * @return array{
 *   enseignant: User,
 *   cours: Cours,
 *   cs: Classe,
 *   groupe: Groupe,
 *   etudiant: User,
 *   typeProjet: TypeProjet,
 *   section: TypeProjetSection,
 *   projet: ProjetRecherche,
 * }
 */
function creerContexteCritereCorrection(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    $cours = Cours::create([
        'nom_cours' => 'Histoire',
        'code' => '330-CC',
        'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);

    $etudiant = User::factory()->create(['role' => 'etudiant']);

    $cs = Classe::create(['cours_id' => $cours->id]);
    $cs->etudiants()->attach($etudiant->id);

    $groupe = Groupe::create([
        'classe_id' => $cs->id,
        'created_by' => $etudiant->id,
    ]);
    $groupe->membres()->attach($etudiant->id);

    $typeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id,
        'cours_id' => $cours->id,
        'nom' => 'TP test',
        'accessible' => true,
    ]);

    $section = TypeProjetSection::create([
        'type_projet_id' => $typeProjet->id,
        'label' => 'Introduction',
        'ordre' => 1,
    ]);

    $projet = ProjetRecherche::create([
        'groupe_id' => $groupe->id,
        'type_projet_id' => $typeProjet->id,
    ]);

    return compact('enseignant', 'cours', 'cs', 'groupe', 'etudiant', 'typeProjet', 'section', 'projet');
}

/**
 * URL de base pour les actions sur les projets.
 */
function urlProjet(Cours $cours, Classe $cs, Groupe $groupe, TypeProjet $tp): string
{
    return "/cours/{$cours->id}/classes/{$cs->id}/groupes/{$groupe->id}/projets/{$tp->id}";
}

// ─── Upsert correction (groupe) ───────────────────────────────────────────────

test("l'enseignant peut appliquer une correction de groupe (user_id = null)", function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'cs' => $cs, 'groupe' => $groupe, 'typeProjet' => $tp, 'section' => $section] = creerContexteCritereCorrection();

    $critere = TypeProjetCritere::create([
        'type_projet_id' => $tp->id, 'section_id' => $section->id,
        'type' => 'positif', 'contenu_type' => 'texte',
        'pointage' => 5.0, 'visible' => true, 'ordre' => 1,
    ]);

    $this->actingAs($enseignant)
        ->putJson(urlProjet($cours, $cs, $groupe, $tp)."/criteres/{$critere->id}/correction", [
            'verifie' => true,
        ])
        ->assertOk()
        ->assertJsonStructure(['message', 'correction']);

    $this->assertDatabaseHas('projet_critere_corrections', [
        'critere_id' => $critere->id,
        'user_id' => null,
        'verifie' => true,
    ]);
});

test("l'enseignant peut appliquer une correction individuelle à un étudiant", function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'cs' => $cs, 'groupe' => $groupe,
        'etudiant' => $etudiant, 'typeProjet' => $tp, 'section' => $section] = creerContexteCritereCorrection();

    $critere = TypeProjetCritere::create([
        'type_projet_id' => $tp->id, 'section_id' => $section->id,
        'type' => 'negatif', 'contenu_type' => 'texte',
        'pointage' => 2.0, 'visible' => true, 'ordre' => 1,
    ]);

    $this->actingAs($enseignant)
        ->putJson(urlProjet($cours, $cs, $groupe, $tp)."/criteres/{$critere->id}/correction", [
            'user_id' => $etudiant->id,
            'points' => 1.5,
            'verifie' => true,
        ])
        ->assertOk();

    $this->assertDatabaseHas('projet_critere_corrections', [
        'critere_id' => $critere->id,
        'user_id' => $etudiant->id,
        'points' => 1.5,
    ]);
});

test('un deuxième PUT sur le même (critère, user) met à jour sans dupliquer', function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'cs' => $cs, 'groupe' => $groupe,
        'typeProjet' => $tp, 'section' => $section] = creerContexteCritereCorrection();

    $critere = TypeProjetCritere::create([
        'type_projet_id' => $tp->id, 'section_id' => $section->id,
        'type' => 'positif', 'contenu_type' => 'texte',
        'pointage' => 5.0, 'visible' => true, 'ordre' => 1,
    ]);

    $url = urlProjet($cours, $cs, $groupe, $tp)."/criteres/{$critere->id}/correction";

    $this->actingAs($enseignant)->putJson($url, ['verifie' => false])->assertOk();
    $this->actingAs($enseignant)->putJson($url, ['verifie' => true, 'commentaire' => 'Parfait'])->assertOk();

    $this->assertDatabaseCount('projet_critere_corrections', 1);
    $this->assertDatabaseHas('projet_critere_corrections', ['verifie' => true, 'commentaire' => 'Parfait']);
});

test('un étudiant ne peut pas appliquer une correction', function () {
    ['cours' => $cours, 'cs' => $cs, 'groupe' => $groupe,
        'etudiant' => $etudiant, 'typeProjet' => $tp, 'section' => $section] = creerContexteCritereCorrection();

    $critere = TypeProjetCritere::create([
        'type_projet_id' => $tp->id, 'section_id' => $section->id,
        'type' => 'positif', 'contenu_type' => 'texte',
        'pointage' => 5.0, 'visible' => true, 'ordre' => 1,
    ]);

    $this->actingAs($etudiant)
        ->putJson(urlProjet($cours, $cs, $groupe, $tp)."/criteres/{$critere->id}/correction", [
            'verifie' => true,
        ])
        ->assertForbidden();
});

// ─── Destroy correction ───────────────────────────────────────────────────────

test("l'enseignant peut supprimer une correction", function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'cs' => $cs, 'groupe' => $groupe,
        'typeProjet' => $tp, 'section' => $section, 'projet' => $projet] = creerContexteCritereCorrection();

    $critere = TypeProjetCritere::create([
        'type_projet_id' => $tp->id, 'section_id' => $section->id,
        'type' => 'positif', 'contenu_type' => 'texte',
        'pointage' => 5.0, 'visible' => true, 'ordre' => 1,
    ]);

    $correction = ProjetCritereCorrection::create([
        'projet_id' => $projet->id, 'critere_id' => $critere->id,
        'user_id' => null, 'verifie' => true,
    ]);

    $this->actingAs($enseignant)
        ->deleteJson(urlProjet($cours, $cs, $groupe, $tp)."/critere-corrections/{$correction->id}")
        ->assertOk();

    $this->assertDatabaseMissing('projet_critere_corrections', ['id' => $correction->id]);
});

test('la suppression d\'une correction supprime aussi ses clones', function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'cs' => $cs, 'groupe' => $groupe,
        'etudiant' => $etudiant, 'typeProjet' => $tp, 'section' => $section, 'projet' => $projet] = creerContexteCritereCorrection();

    $critere = TypeProjetCritere::create([
        'type_projet_id' => $tp->id, 'section_id' => $section->id,
        'type' => 'positif', 'contenu_type' => 'texte',
        'pointage' => 5.0, 'visible' => true, 'ordre' => 1,
    ]);

    $source = ProjetCritereCorrection::create([
        'projet_id' => $projet->id, 'critere_id' => $critere->id,
        'user_id' => null, 'verifie' => true,
    ]);

    $clone = ProjetCritereCorrection::create([
        'projet_id' => $projet->id, 'critere_id' => $critere->id,
        'user_id' => $etudiant->id, 'verifie' => false,
        'source_id' => $source->id,
    ]);

    $this->actingAs($enseignant)
        ->deleteJson(urlProjet($cours, $cs, $groupe, $tp)."/critere-corrections/{$source->id}")
        ->assertOk();

    $this->assertDatabaseMissing('projet_critere_corrections', ['id' => $source->id]);
    $this->assertDatabaseMissing('projet_critere_corrections', ['id' => $clone->id]);
});

// ─── Cloner correction ────────────────────────────────────────────────────────

test("l'enseignant peut cloner une correction pour donner des points différents à un étudiant", function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'cs' => $cs, 'groupe' => $groupe,
        'etudiant' => $etudiant, 'typeProjet' => $tp, 'section' => $section, 'projet' => $projet] = creerContexteCritereCorrection();

    $critere = TypeProjetCritere::create([
        'type_projet_id' => $tp->id, 'section_id' => $section->id,
        'type' => 'positif', 'contenu_type' => 'texte',
        'pointage' => 5.0, 'visible' => true, 'ordre' => 1,
    ]);

    $source = ProjetCritereCorrection::create([
        'projet_id' => $projet->id, 'critere_id' => $critere->id,
        'user_id' => null, 'verifie' => true,
    ]);

    $this->actingAs($enseignant)
        ->postJson(urlProjet($cours, $cs, $groupe, $tp)."/critere-corrections/{$source->id}/cloner", [
            'user_id' => $etudiant->id,
            'points' => 3.0,
            'verifie' => true,
        ])
        ->assertOk()
        ->assertJson(['message' => 'cloned']);

    $this->assertDatabaseHas('projet_critere_corrections', [
        'critere_id' => $critere->id,
        'user_id' => $etudiant->id,
        'points' => 3.0,
        'source_id' => $source->id,
    ]);
});

test('le clonage remplace tout clone existant pour le même (critère, étudiant)', function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'cs' => $cs, 'groupe' => $groupe,
        'etudiant' => $etudiant, 'typeProjet' => $tp, 'section' => $section, 'projet' => $projet] = creerContexteCritereCorrection();

    $critere = TypeProjetCritere::create([
        'type_projet_id' => $tp->id, 'section_id' => $section->id,
        'type' => 'positif', 'contenu_type' => 'texte',
        'pointage' => 5.0, 'visible' => true, 'ordre' => 1,
    ]);

    $source = ProjetCritereCorrection::create([
        'projet_id' => $projet->id, 'critere_id' => $critere->id,
        'user_id' => null, 'verifie' => true,
    ]);

    // Créer un premier clone
    $this->actingAs($enseignant)
        ->postJson(urlProjet($cours, $cs, $groupe, $tp)."/critere-corrections/{$source->id}/cloner", [
            'user_id' => $etudiant->id, 'points' => 2.0, 'verifie' => true,
        ])
        ->assertOk();

    // Recréer un clone pour le même étudiant — l'ancien est remplacé
    $this->actingAs($enseignant)
        ->postJson(urlProjet($cours, $cs, $groupe, $tp)."/critere-corrections/{$source->id}/cloner", [
            'user_id' => $etudiant->id, 'points' => 4.0, 'verifie' => true,
        ])
        ->assertOk();

    // Un seul clone individuel pour cet étudiant
    expect(ProjetCritereCorrection::where('critere_id', $critere->id)
        ->where('user_id', $etudiant->id)
        ->count()
    )->toBe(1);

    $this->assertDatabaseHas('projet_critere_corrections', [
        'critere_id' => $critere->id,
        'user_id' => $etudiant->id,
        'points' => 4.0,
    ]);
});

// ─── Toggle coche étudiant ────────────────────────────────────────────────────

test('un étudiant membre peut cocher un critère visible', function () {
    ['cours' => $cours, 'cs' => $cs, 'groupe' => $groupe,
        'etudiant' => $etudiant, 'typeProjet' => $tp, 'section' => $section, 'projet' => $projet] = creerContexteCritereCorrection();

    $critere = TypeProjetCritere::create([
        'type_projet_id' => $tp->id, 'section_id' => $section->id,
        'type' => 'positif', 'contenu_type' => 'texte',
        'pointage' => 5.0, 'visible' => true, 'ordre' => 1,
    ]);

    $this->actingAs($etudiant)
        ->patchJson(urlProjet($cours, $cs, $groupe, $tp)."/criteres/{$critere->id}/coche")
        ->assertOk()
        ->assertJson(['coche' => true]);

    $this->assertDatabaseHas('projet_critere_coches', [
        'projet_id' => $projet->id,
        'critere_id' => $critere->id,
        'user_id' => $etudiant->id,
    ]);
});

test('un deuxième toggle sur la même coche la décoche', function () {
    ['cours' => $cours, 'cs' => $cs, 'groupe' => $groupe,
        'etudiant' => $etudiant, 'typeProjet' => $tp, 'section' => $section, 'projet' => $projet] = creerContexteCritereCorrection();

    $critere = TypeProjetCritere::create([
        'type_projet_id' => $tp->id, 'section_id' => $section->id,
        'type' => 'positif', 'contenu_type' => 'texte',
        'pointage' => 5.0, 'visible' => true, 'ordre' => 1,
    ]);

    $url = urlProjet($cours, $cs, $groupe, $tp)."/criteres/{$critere->id}/coche";

    $this->actingAs($etudiant)->patchJson($url)->assertJson(['coche' => true]);
    $this->actingAs($etudiant)->patchJson($url)->assertJson(['coche' => false]);

    $this->assertDatabaseMissing('projet_critere_coches', [
        'critere_id' => $critere->id,
        'user_id' => $etudiant->id,
    ]);
});

test("l'enseignant ne peut pas cocher un critère (réservé aux membres)", function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'cs' => $cs, 'groupe' => $groupe,
        'typeProjet' => $tp, 'section' => $section] = creerContexteCritereCorrection();

    $critere = TypeProjetCritere::create([
        'type_projet_id' => $tp->id, 'section_id' => $section->id,
        'type' => 'positif', 'contenu_type' => 'texte',
        'pointage' => 5.0, 'visible' => true, 'ordre' => 1,
    ]);

    $this->actingAs($enseignant)
        ->patchJson(urlProjet($cours, $cs, $groupe, $tp)."/criteres/{$critere->id}/coche")
        ->assertForbidden();
});

test('un étudiant ne peut pas cocher un critère non visible', function () {
    ['cours' => $cours, 'cs' => $cs, 'groupe' => $groupe,
        'etudiant' => $etudiant, 'typeProjet' => $tp, 'section' => $section] = creerContexteCritereCorrection();

    $critere = TypeProjetCritere::create([
        'type_projet_id' => $tp->id, 'section_id' => $section->id,
        'type' => 'positif', 'contenu_type' => 'texte',
        'pointage' => 5.0, 'visible' => false, 'ordre' => 1,
    ]);

    $this->actingAs($etudiant)
        ->patchJson(urlProjet($cours, $cs, $groupe, $tp)."/criteres/{$critere->id}/coche")
        ->assertForbidden();
});

// ─── Visibilité corrections (show) ────────────────────────────────────────────

test("la page show() inclut les critères et les corrections pour l'enseignant", function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'cs' => $cs, 'groupe' => $groupe,
        'typeProjet' => $tp, 'section' => $section, 'projet' => $projet] = creerContexteCritereCorrection();

    $critere = TypeProjetCritere::create([
        'type_projet_id' => $tp->id, 'section_id' => $section->id,
        'type' => 'positif', 'contenu_type' => 'texte',
        'pointage' => 5.0, 'visible' => true, 'ordre' => 1,
    ]);

    ProjetCritereCorrection::create([
        'projet_id' => $projet->id, 'critere_id' => $critere->id,
        'user_id' => null, 'verifie' => true,
    ]);

    $this->actingAs($enseignant)
        ->get(urlProjet($cours, $cs, $groupe, $tp).'/edit')
        ->assertInertia(fn ($page) => $page
            ->component('Projets/Show')
            ->has('criteresGlobaux')
            ->has('correctionsParCritere')
            ->has('cochesUtilisateur')
        );
});

test('un étudiant ne voit pas les corrections si correction_visible = false', function () {
    ['cours' => $cours, 'cs' => $cs, 'groupe' => $groupe,
        'etudiant' => $etudiant, 'typeProjet' => $tp, 'section' => $section, 'projet' => $projet] = creerContexteCritereCorrection();

    $critere = TypeProjetCritere::create([
        'type_projet_id' => $tp->id, 'section_id' => $section->id,
        'type' => 'negatif', 'contenu_type' => 'texte',
        'pointage' => 2.0, 'visible' => true, 'ordre' => 1,
    ]);

    ProjetCritereCorrection::create([
        'projet_id' => $projet->id, 'critere_id' => $critere->id,
        'user_id' => null, 'verifie' => true,
    ]);

    // correction_visible = false (défaut)
    $this->actingAs($etudiant)
        ->get(urlProjet($cours, $cs, $groupe, $tp).'/edit')
        ->assertInertia(fn ($page) => $page
            ->component('Projets/Show')
            ->where('correctionsParCritere', [])
        );
});

test("un étudiant ne voit que ses propres corrections (pas celles d'un autre étudiant)", function () {
    ['cours' => $cours, 'cs' => $cs, 'groupe' => $groupe,
        'etudiant' => $etudiant, 'typeProjet' => $tp, 'section' => $section, 'projet' => $projet] = creerContexteCritereCorrection();

    $autreEtudiant = User::factory()->create(['role' => 'etudiant']);
    $cs->etudiants()->attach($autreEtudiant->id);
    $groupe->membres()->attach($autreEtudiant->id);

    $critere = TypeProjetCritere::create([
        'type_projet_id' => $tp->id, 'section_id' => $section->id,
        'type' => 'negatif', 'contenu_type' => 'texte',
        'pointage' => 2.0, 'visible' => true, 'ordre' => 1,
    ]);

    // Correction pour l'autre étudiant
    $correctionAutre = ProjetCritereCorrection::create([
        'projet_id' => $projet->id, 'critere_id' => $critere->id,
        'user_id' => $autreEtudiant->id, 'verifie' => true,
    ]);

    // Activer la visibilité des corrections
    $projet->update(['correction_visible' => true]);

    $this->actingAs($etudiant)
        ->get(urlProjet($cours, $cs, $groupe, $tp).'/edit')
        ->assertInertia(fn ($page) => $page
            ->component('Projets/Show')
            // correctionsParCritere ne contient pas la correction de l'autre étudiant
            ->where('correctionsParCritere', [])
        );
});
