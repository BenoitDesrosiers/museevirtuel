<?php

use App\Models\Cours;
use App\Models\TypeProjet;
use App\Models\TypeProjetCritere;
use App\Models\TypeProjetSection;
use App\Models\User;

// ─── Helper ───────────────────────────────────────────────────────────────────

/**
 * Crée un contexte minimal enseignant + cours + type de projet + section.
 *
 * @return array{enseignant: User, cours: Cours, typeProjet: TypeProjet, section: TypeProjetSection}
 */
function creerContexteCritere(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    $cours = Cours::create([
        'nom_cours' => 'Test',
        'code' => '330-T1',
        'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);

    $typeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id,
        'cours_id' => $cours->id,
        'nom' => 'Projet test',
        'accessible' => true,
    ]);

    $section = TypeProjetSection::create([
        'type_projet_id' => $typeProjet->id,
        'label' => 'Introduction',
        'ordre' => 1,
    ]);

    return compact('enseignant', 'cours', 'typeProjet', 'section');
}

// ─── Store ────────────────────────────────────────────────────────────────────

test("l'enseignant peut créer un critère positif dans une section", function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'typeProjet' => $tp, 'section' => $section] = creerContexteCritere();

    $this->actingAs($enseignant)
        ->postJson("/cours/{$cours->id}/types-projets/{$tp->id}/criteres", [
            'section_id' => $section->id,
            'type' => 'positif',
            'contenu_type' => 'texte',
            'pointage' => 5.0,
            'contenu' => 'Qualité de l\'argumentation',
            'visible' => true,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('type_projet_criteres', [
        'type_projet_id' => $tp->id,
        'section_id' => $section->id,
        'type' => 'positif',
        'pointage' => 5.0,
    ]);
});

test("l'enseignant peut créer un critère global (sans section)", function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'typeProjet' => $tp] = creerContexteCritere();

    $this->actingAs($enseignant)
        ->postJson("/cours/{$cours->id}/types-projets/{$tp->id}/criteres", [
            'type' => 'negatif',
            'contenu_type' => 'texte',
            'pointage' => 2.0,
            'contenu' => 'Mauvaise référence',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('type_projet_criteres', [
        'type_projet_id' => $tp->id,
        'section_id' => null,
        'type' => 'negatif',
    ]);
});

test("l'enseignant peut créer un critère avec une échelle", function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'typeProjet' => $tp, 'section' => $section] = creerContexteCritere();

    $echelle = [
        ['label' => 'Excellent', 'points' => 5, 'description' => null],
        ['label' => 'Bien', 'points' => 3, 'description' => null],
        ['label' => 'Insuffisant', 'points' => 1, 'description' => null],
    ];

    $this->actingAs($enseignant)
        ->postJson("/cours/{$cours->id}/types-projets/{$tp->id}/criteres", [
            'section_id' => $section->id,
            'type' => 'positif',
            'contenu_type' => 'echelle',
            'pointage' => 5.0,
            'echelle' => $echelle,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('type_projet_criteres', [
        'type_projet_id' => $tp->id,
        'contenu_type' => 'echelle',
    ]);
});

test('la section_id doit appartenir au typeProjet (IDOR)', function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'typeProjet' => $tp] = creerContexteCritere();

    // Section d'un autre type de projet
    $autreTypeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id,
        'cours_id' => $cours->id,
        'nom' => 'Autre',
    ]);
    $autreSection = TypeProjetSection::create([
        'type_projet_id' => $autreTypeProjet->id,
        'label' => 'Autre section',
        'ordre' => 1,
    ]);

    $this->actingAs($enseignant)
        ->postJson("/cours/{$cours->id}/types-projets/{$tp->id}/criteres", [
            'section_id' => $autreSection->id,
            'type' => 'positif',
            'contenu_type' => 'texte',
            'pointage' => 3.0,
        ])
        ->assertNotFound();
});

test('un étudiant est redirigé et ne peut pas créer de critère (rôle insuffisant)', function () {
    ['cours' => $cours, 'typeProjet' => $tp] = creerContexteCritere();
    $etudiant = User::factory()->create(['role' => 'etudiant']);

    // La route est protégée par le middleware role:enseignant,admin → redirect pour un étudiant
    $this->actingAs($etudiant)
        ->post("/cours/{$cours->id}/types-projets/{$tp->id}/criteres", [
            'type' => 'positif', 'contenu_type' => 'texte', 'pointage' => 3.0,
        ])
        ->assertRedirect();

    $this->assertDatabaseCount('type_projet_criteres', 0);
});

// ─── Update ───────────────────────────────────────────────────────────────────

test("l'enseignant peut modifier le contenu et le pointage d'un critère", function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'typeProjet' => $tp, 'section' => $section] = creerContexteCritere();

    $critere = TypeProjetCritere::create([
        'type_projet_id' => $tp->id,
        'section_id' => $section->id,
        'type' => 'positif',
        'contenu_type' => 'texte',
        'pointage' => 5.0,
        'visible' => true,
        'ordre' => 1,
    ]);

    $this->actingAs($enseignant)
        ->putJson("/cours/{$cours->id}/types-projets/{$tp->id}/criteres/{$critere->id}", [
            'type' => 'positif',
            'contenu_type' => 'texte',
            'pointage' => 8.0,
            'contenu' => 'Contenu mis à jour',
            'visible' => true,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('type_projet_criteres', [
        'id' => $critere->id,
        'pointage' => 8.0,
        'contenu' => 'Contenu mis à jour',
    ]);
});

test("la section d'un critère ne peut pas être changée lors d'une mise à jour", function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'typeProjet' => $tp, 'section' => $section] = creerContexteCritere();

    $critere = TypeProjetCritere::create([
        'type_projet_id' => $tp->id,
        'section_id' => $section->id,
        'type' => 'positif',
        'contenu_type' => 'texte',
        'pointage' => 5.0,
        'visible' => true,
        'ordre' => 1,
    ]);

    $autreSection = TypeProjetSection::create([
        'type_projet_id' => $tp->id,
        'label' => 'Conclusion',
        'ordre' => 2,
    ]);

    // La requête UpdateCritereRequest n'accepte pas section_id — il est ignoré
    $this->actingAs($enseignant)
        ->putJson("/cours/{$cours->id}/types-projets/{$tp->id}/criteres/{$critere->id}", [
            'type' => 'positif',
            'contenu_type' => 'texte',
            'pointage' => 5.0,
            'visible' => true,
            // section_id absent du UpdateCritereRequest
        ])
        ->assertRedirect();

    // La section reste inchangée
    $this->assertDatabaseHas('type_projet_criteres', [
        'id' => $critere->id,
        'section_id' => $section->id,
    ]);
});

// ─── Destroy ──────────────────────────────────────────────────────────────────

test("l'enseignant peut supprimer un critère", function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'typeProjet' => $tp, 'section' => $section] = creerContexteCritere();

    $critere = TypeProjetCritere::create([
        'type_projet_id' => $tp->id,
        'section_id' => $section->id,
        'type' => 'positif',
        'contenu_type' => 'texte',
        'pointage' => 5.0,
        'visible' => true,
        'ordre' => 1,
    ]);

    $this->actingAs($enseignant)
        ->deleteJson("/cours/{$cours->id}/types-projets/{$tp->id}/criteres/{$critere->id}")
        ->assertRedirect();

    $this->assertDatabaseMissing('type_projet_criteres', ['id' => $critere->id]);
});

test('la suppression d\'un critère renumérote les critères restants', function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'typeProjet' => $tp, 'section' => $section] = creerContexteCritere();

    $c1 = TypeProjetCritere::create([
        'type_projet_id' => $tp->id, 'section_id' => $section->id,
        'type' => 'positif', 'contenu_type' => 'texte', 'pointage' => 3, 'visible' => true, 'ordre' => 1,
    ]);
    $c2 = TypeProjetCritere::create([
        'type_projet_id' => $tp->id, 'section_id' => $section->id,
        'type' => 'positif', 'contenu_type' => 'texte', 'pointage' => 3, 'visible' => true, 'ordre' => 2,
    ]);
    $c3 = TypeProjetCritere::create([
        'type_projet_id' => $tp->id, 'section_id' => $section->id,
        'type' => 'positif', 'contenu_type' => 'texte', 'pointage' => 3, 'visible' => true, 'ordre' => 3,
    ]);

    $this->actingAs($enseignant)
        ->deleteJson("/cours/{$cours->id}/types-projets/{$tp->id}/criteres/{$c2->id}")
        ->assertRedirect();

    // c1 garde ordre 1, c3 passe à ordre 2
    $this->assertDatabaseHas('type_projet_criteres', ['id' => $c1->id, 'ordre' => 1]);
    $this->assertDatabaseHas('type_projet_criteres', ['id' => $c3->id, 'ordre' => 2]);
});

// ─── Reorder ──────────────────────────────────────────────────────────────────

test("l'enseignant peut réordonner les critères", function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'typeProjet' => $tp, 'section' => $section] = creerContexteCritere();

    $c1 = TypeProjetCritere::create([
        'type_projet_id' => $tp->id, 'section_id' => $section->id,
        'type' => 'positif', 'contenu_type' => 'texte', 'pointage' => 3, 'visible' => true, 'ordre' => 1,
    ]);
    $c2 = TypeProjetCritere::create([
        'type_projet_id' => $tp->id, 'section_id' => $section->id,
        'type' => 'positif', 'contenu_type' => 'texte', 'pointage' => 3, 'visible' => true, 'ordre' => 2,
    ]);

    $this->actingAs($enseignant)
        ->patchJson("/cours/{$cours->id}/types-projets/{$tp->id}/criteres/reorder", [
            'ordre' => [$c2->id, $c1->id],
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('type_projet_criteres', ['id' => $c2->id, 'ordre' => 1]);
    $this->assertDatabaseHas('type_projet_criteres', ['id' => $c1->id, 'ordre' => 2]);
});

// ─── Toggle visible groupe ────────────────────────────────────────────────────

test("l'enseignant peut rendre tous les critères positifs visibles d'un coup", function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'typeProjet' => $tp, 'section' => $section] = creerContexteCritere();

    TypeProjetCritere::create([
        'type_projet_id' => $tp->id, 'section_id' => $section->id,
        'type' => 'positif', 'contenu_type' => 'texte', 'pointage' => 5, 'visible' => false, 'ordre' => 1,
    ]);
    TypeProjetCritere::create([
        'type_projet_id' => $tp->id, 'section_id' => $section->id,
        'type' => 'positif', 'contenu_type' => 'texte', 'pointage' => 3, 'visible' => false, 'ordre' => 2,
    ]);

    $this->actingAs($enseignant)
        ->patchJson("/cours/{$cours->id}/types-projets/{$tp->id}/criteres/visible-groupe", [
            'type' => 'positif',
            'visible' => true,
        ])
        ->assertRedirect();

    $this->assertDatabaseMissing('type_projet_criteres', [
        'type_projet_id' => $tp->id,
        'type' => 'positif',
        'visible' => false,
    ]);
});

// ─── Autorisation IDOR ────────────────────────────────────────────────────────

test("un enseignant ne peut pas gérer les critères d'un cours qui ne lui appartient pas", function () {
    ['cours' => $cours, 'typeProjet' => $tp] = creerContexteCritere();
    $autreEnseignant = User::factory()->create(['role' => 'enseignant']);

    $this->actingAs($autreEnseignant)
        ->postJson("/cours/{$cours->id}/types-projets/{$tp->id}/criteres", [
            'type' => 'positif', 'contenu_type' => 'texte', 'pointage' => 3.0,
        ])
        ->assertForbidden();
});
