<?php

use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetAnnotation;
use App\Models\ProjetConclusion;
use App\Models\ProjetNote;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use App\Models\TypeProjetSection;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Crée les entités nécessaires à un scénario de test :
 * un enseignant, un type de projet accessible, une section de cours, un groupe et des étudiants membres.
 *
 * @return array{enseignant: User, typeProjet: TypeProjet, cours: Cours, classeSection: Classe, classe: Groupe, etudiant1: User, etudiant2: User}
 */
function creerScenario(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    $typeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id,
        'nom' => 'Projet test',
        'accessible' => true,
    ]);

    $cours = Cours::create([
        'nom_cours' => 'Histoire du Québec',
        'description' => 'Test',
        'heures_par_semaine' => 3,
        'code' => '330-2E1',
        'groupe' => '0001',
        'enseignant_id' => $enseignant->id,
    ]);

    $etudiant1 = User::factory()->create(['role' => 'etudiant']);
    $etudiant2 = User::factory()->create(['role' => 'etudiant']);

    $classeSection = Classe::create(['cours_id' => $cours->id]);
    $classeSection->etudiants()->attach([$etudiant1->id, $etudiant2->id]);

    $classe = Groupe::create([
        'classe_id' => $classeSection->id,
        'created_by' => $etudiant1->id,
    ]);
    $classe->membres()->attach([$etudiant1->id, $etudiant2->id]);

    return compact('enseignant', 'typeProjet', 'cours', 'classeSection', 'classe', 'etudiant1', 'etudiant2');
}

// ─── Accès à l'index ──────────────────────────────────────────────────────────

test("un membre du groupe peut accéder à l'index des projets", function () {
    ['cours' => $cours, 'classeSection' => $cs, 'classe' => $classe, 'etudiant1' => $etudiant] = creerScenario();

    $this->actingAs($etudiant)
        ->get("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets")
        ->assertOk();
});

test("l'enseignant de la classe peut accéder à l'index des projets", function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classeSection' => $cs, 'classe' => $classe] = creerScenario();

    $this->actingAs($enseignant)
        ->get("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets")
        ->assertOk();
});

test('un invité non authentifié est redirigé vers login', function () {
    ['cours' => $cours, 'classeSection' => $cs, 'classe' => $classe] = creerScenario();

    $this->get("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets")
        ->assertRedirect(route('login'));
});

test("un étudiant extérieur au groupe ne peut pas accéder à l'index", function () {
    ['cours' => $cours, 'classeSection' => $cs, 'classe' => $classe] = creerScenario();

    $etranger = User::factory()->create(['role' => 'etudiant']);

    $this->actingAs($etranger)
        ->get("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets")
        ->assertForbidden();
});

// ─── Accès à la page show (projet partagé) ────────────────────────────────────

test('un membre peut accéder à la page du projet partagé', function () {
    ['cours' => $cours, 'classeSection' => $cs, 'classe' => $classe, 'etudiant1' => $etudiant, 'typeProjet' => $typeProjet] = creerScenario();

    $this->actingAs($etudiant)
        ->get("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/edit")
        ->assertOk();
});

test("l'enseignant peut consulter le projet partagé en lecture seule", function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classeSection' => $cs, 'classe' => $classe, 'typeProjet' => $typeProjet] = creerScenario();

    $this->actingAs($enseignant)
        ->get("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/edit")
        ->assertOk();
});

// ─── Mise à jour du contenu partagé ───────────────────────────────────────────

test('un membre peut enregistrer le contenu partagé du projet', function () {
    ['cours' => $cours, 'classeSection' => $cs, 'classe' => $classe, 'etudiant1' => $etudiant, 'typeProjet' => $typeProjet] = creerScenario();

    $this->actingAs($etudiant)
        ->putJson("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}", [
            'titre_projet' => 'L\'agriculture québécoise',
            'introduction_amener' => '<p>Contexte général…</p>',
            'dev_1_titre' => 'Les origines',
            'dev_1_contenu' => '<p>Contenu du paragraphe 1…</p>',
        ])
        ->assertOk()
        ->assertJsonStructure(['message', 'completion']);

    $this->assertDatabaseHas('projets_recherche', [
        'groupe_id' => $classe->id,
        'titre_projet' => 'L\'agriculture québécoise',
    ]);
});

test('un deuxième PUT met à jour sans créer de doublon', function () {
    ['cours' => $cours, 'classeSection' => $cs, 'classe' => $classe, 'etudiant1' => $etudiant, 'typeProjet' => $typeProjet] = creerScenario();

    $url = "/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}";

    $this->actingAs($etudiant)->putJson($url, ['titre_projet' => 'Titre V1']);
    $this->actingAs($etudiant)->putJson($url, ['titre_projet' => 'Titre V2']);

    // Un seul projet par (groupe × typeProjet)
    expect(ProjetRecherche::where('groupe_id', $classe->id)->where('type_projet_id', $typeProjet->id)->count())->toBe(1);
    $this->assertDatabaseHas('projets_recherche', ['titre_projet' => 'Titre V2']);
});

test("l'enseignant ne peut pas modifier le contenu partagé", function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classeSection' => $cs, 'classe' => $classe, 'typeProjet' => $typeProjet] = creerScenario();

    $this->actingAs($enseignant)
        ->putJson("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}", [
            'titre_projet' => 'Modification non autorisée',
        ])
        ->assertForbidden();
});

// ─── Conclusion individuelle ───────────────────────────────────────────────────

test('un membre peut enregistrer sa conclusion individuelle', function () {
    ['cours' => $cours, 'classeSection' => $cs, 'classe' => $classe, 'etudiant1' => $etudiant, 'typeProjet' => $typeProjet] = creerScenario();

    $this->actingAs($etudiant)
        ->putJson("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/conclusion", [
            'user_id' => $etudiant->id,
            'contenu' => '<p>Ma conclusion personnelle…</p>',
        ])
        ->assertOk()
        ->assertJson(['message' => 'saved']);

    $projet = ProjetRecherche::where('groupe_id', $classe->id)->first();
    $this->assertDatabaseHas('projet_conclusions', [
        'projet_id' => $projet->id,
        'user_id' => $etudiant->id,
    ]);
});

test('un membre peut modifier la conclusion d\'un compagnon de groupe', function () {
    ['cours' => $cours, 'classeSection' => $cs, 'classe' => $classe, 'etudiant1' => $e1, 'etudiant2' => $e2, 'typeProjet' => $typeProjet] = creerScenario();

    $url = "/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/conclusion";

    // E1 modifie la conclusion de E2
    $this->actingAs($e1)
        ->putJson($url, [
            'user_id' => $e2->id,
            'contenu' => '<p>Conclusion écrite par E1 pour E2</p>',
        ])
        ->assertOk();

    $projet = ProjetRecherche::where('groupe_id', $classe->id)->first();
    $this->assertDatabaseHas('projet_conclusions', [
        'projet_id' => $projet->id,
        'user_id' => $e2->id,
    ]);
});

test('chaque membre a sa propre conclusion distincte', function () {
    ['cours' => $cours, 'classeSection' => $cs, 'classe' => $classe, 'etudiant1' => $e1, 'etudiant2' => $e2, 'typeProjet' => $typeProjet] = creerScenario();

    $url = "/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/conclusion";

    $this->actingAs($e1)->putJson($url, ['user_id' => $e1->id, 'contenu' => '<p>Conclusion de E1</p>']);
    $this->actingAs($e2)->putJson($url, ['user_id' => $e2->id, 'contenu' => '<p>Conclusion de E2</p>']);

    $projet = ProjetRecherche::where('groupe_id', $classe->id)->first();

    expect(ProjetConclusion::where('projet_id', $projet->id)->count())->toBe(2);
});

test('un étudiant extérieur ne peut pas enregistrer une conclusion', function () {
    ['cours' => $cours, 'classeSection' => $cs, 'classe' => $classe, 'typeProjet' => $typeProjet] = creerScenario();

    $etranger = User::factory()->create(['role' => 'etudiant']);

    $this->actingAs($etranger)
        ->putJson("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/conclusion", [
            'user_id' => $etranger->id,
            'contenu' => '<p>Intrusion</p>',
        ])
        ->assertForbidden();
});

test('un membre ne peut pas cibler un user_id hors de son groupe (422)', function () {
    ['cours' => $cours, 'classeSection' => $cs, 'classe' => $classe, 'etudiant1' => $e1, 'typeProjet' => $typeProjet] = creerScenario();

    $horsGroupe = User::factory()->create(['role' => 'etudiant']);

    $this->actingAs($e1)
        ->putJson("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/conclusion", [
            'user_id' => $horsGroupe->id,
            'contenu' => '<p>Tentative IDOR</p>',
        ])
        ->assertUnprocessable();
});

// ─── Export ───────────────────────────────────────────────────────────────────

test('un membre peut exporter le projet de groupe en PDF', function () {
    ['cours' => $cours, 'classeSection' => $cs, 'classe' => $classe, 'etudiant1' => $etudiant, 'typeProjet' => $typeProjet] = creerScenario();

    // Le controller fait firstOrFail() — il faut qu'un projet existe
    ProjetRecherche::create(['groupe_id' => $classe->id, 'type_projet_id' => $typeProjet->id]);

    $this->actingAs($etudiant)
        ->get("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/pdf")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

test('un membre peut exporter le projet de groupe en Word', function () {
    ['cours' => $cours, 'classeSection' => $cs, 'classe' => $classe, 'etudiant1' => $etudiant, 'typeProjet' => $typeProjet] = creerScenario();

    // Le controller fait firstOrFail() — il faut qu'un projet existe
    ProjetRecherche::create(['groupe_id' => $classe->id, 'type_projet_id' => $typeProjet->id]);

    $this->actingAs($etudiant)
        ->get("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/word")
        ->assertOk()
        ->assertHeader(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        );
});

// ─── Verrouillage du document ──────────────────────────────────────────────────

test("l'enseignant peut verrouiller un document", function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classeSection' => $cs, 'classe' => $classe, 'typeProjet' => $typeProjet] = creerScenario();

    $this->actingAs($enseignant)
        ->patchJson("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/verrouille")
        ->assertOk()
        ->assertJson(['message' => 'toggled', 'verrouille' => true]);

    $this->assertDatabaseHas('projets_recherche', ['groupe_id' => $classe->id, 'verrouille' => true]);
});

test('le toggle déverrouille un document déjà verrouillé', function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classeSection' => $cs, 'classe' => $classe, 'typeProjet' => $typeProjet] = creerScenario();

    ProjetRecherche::create(['groupe_id' => $classe->id, 'type_projet_id' => $typeProjet->id, 'verrouille' => true]);

    $this->actingAs($enseignant)
        ->patchJson("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/verrouille")
        ->assertOk()
        ->assertJson(['verrouille' => false]);
});

test('un étudiant ne peut pas verrouiller un document', function () {
    ['cours' => $cours, 'classeSection' => $cs, 'classe' => $classe, 'etudiant1' => $etudiant, 'typeProjet' => $typeProjet] = creerScenario();

    $this->actingAs($etudiant)
        ->patchJson("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/verrouille")
        ->assertForbidden();
});

test('un étudiant ne peut pas modifier un projet verrouillé', function () {
    ['cours' => $cours, 'classeSection' => $cs, 'classe' => $classe, 'etudiant1' => $etudiant, 'typeProjet' => $typeProjet] = creerScenario();

    ProjetRecherche::create(['groupe_id' => $classe->id, 'type_projet_id' => $typeProjet->id, 'verrouille' => true]);

    $this->actingAs($etudiant)
        ->putJson("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}", [
            'titre_projet' => 'Modification bloquée',
        ])
        ->assertForbidden();
});

test('peutEditer est false quand le document est verrouillé', function () {
    ['cours' => $cours, 'classeSection' => $cs, 'classe' => $classe, 'etudiant1' => $etudiant, 'typeProjet' => $typeProjet] = creerScenario();

    ProjetRecherche::create(['groupe_id' => $classe->id, 'type_projet_id' => $typeProjet->id, 'verrouille' => true]);

    $this->actingAs($etudiant)
        ->get("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/edit")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('peutEditer', false)
            ->where('verrouille', true)
        );
});

// ─── Visibilité des corrections ────────────────────────────────────────────────

test("l'enseignant peut activer la visibilité des corrections", function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classeSection' => $cs, 'classe' => $classe, 'typeProjet' => $typeProjet] = creerScenario();

    $this->actingAs($enseignant)
        ->patchJson("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/correction-visible")
        ->assertOk()
        ->assertJson(['message' => 'toggled', 'correction_visible' => true]);
});

test('un étudiant ne peut pas modifier la visibilité des corrections', function () {
    ['cours' => $cours, 'classeSection' => $cs, 'classe' => $classe, 'etudiant1' => $etudiant, 'typeProjet' => $typeProjet] = creerScenario();

    $this->actingAs($etudiant)
        ->patchJson("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/correction-visible")
        ->assertForbidden();
});

test('un étudiant ne voit pas les annotations de type correction si correction_visible est false', function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classeSection' => $cs, 'classe' => $classe, 'etudiant1' => $etudiant, 'typeProjet' => $typeProjet] = creerScenario();

    $projet = ProjetRecherche::create(['groupe_id' => $classe->id, 'type_projet_id' => $typeProjet->id, 'correction_visible' => false]);

    ProjetAnnotation::create([
        'projet_id' => $projet->id,
        'champ' => 'introduction_amener',
        'commentaire_id' => Str::uuid(),
        'contenu' => 'Commentaire visible',
        'type' => 'commentaire',
        'user_id' => $enseignant->id,
    ]);

    ProjetAnnotation::create([
        'projet_id' => $projet->id,
        'champ' => 'introduction_amener',
        'commentaire_id' => Str::uuid(),
        'contenu' => 'Correction masquée',
        'type' => 'correction',
        'user_id' => $enseignant->id,
    ]);

    $this->actingAs($etudiant)
        ->get("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/edit")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('annotationsParChamp.introduction_amener', 1)
            ->where('annotationsParChamp.introduction_amener.0.type', 'commentaire')
        );
});

test('un étudiant voit les corrections si correction_visible est true', function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classeSection' => $cs, 'classe' => $classe, 'etudiant1' => $etudiant, 'typeProjet' => $typeProjet] = creerScenario();

    $projet = ProjetRecherche::create(['groupe_id' => $classe->id, 'type_projet_id' => $typeProjet->id, 'correction_visible' => true]);

    ProjetAnnotation::create([
        'projet_id' => $projet->id,
        'champ' => 'introduction_amener',
        'commentaire_id' => Str::uuid(),
        'contenu' => 'Correction visible',
        'type' => 'correction',
        'user_id' => $enseignant->id,
    ]);

    $this->actingAs($etudiant)
        ->get("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/edit")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('annotationsParChamp.introduction_amener', 1)
            ->where('annotationsParChamp.introduction_amener.0.type', 'correction')
        );
});

test("l'enseignant voit toujours toutes les annotations, peu importe correction_visible", function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classeSection' => $cs, 'classe' => $classe, 'typeProjet' => $typeProjet] = creerScenario();

    $projet = ProjetRecherche::create(['groupe_id' => $classe->id, 'type_projet_id' => $typeProjet->id, 'correction_visible' => false]);

    ProjetAnnotation::create([
        'projet_id' => $projet->id,
        'champ' => 'introduction_amener',
        'commentaire_id' => Str::uuid(),
        'contenu' => 'Correction',
        'type' => 'correction',
        'user_id' => $enseignant->id,
    ]);

    $this->actingAs($enseignant)
        ->get("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/edit")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('annotationsParChamp.introduction_amener', 1)
        );
});

// ─── Remise de travail ─────────────────────────────────────────────────────────

test('un membre peut remettre son travail', function () {
    ['cours' => $cours, 'classeSection' => $cs, 'classe' => $classe, 'etudiant1' => $etudiant, 'typeProjet' => $typeProjet] = creerScenario();

    $this->actingAs($etudiant)
        ->postJson("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/remettre")
        ->assertOk()
        ->assertJsonStructure(['message', 'remis_le'])
        ->assertJson(['message' => 'remis']);

    $projet = ProjetRecherche::where('groupe_id', $classe->id)->first();
    expect($projet->remis_le)->not->toBeNull();
});

test('un étudiant hors groupe ne peut pas remettre le travail', function () {
    ['cours' => $cours, 'classeSection' => $cs, 'classe' => $classe, 'typeProjet' => $typeProjet] = creerScenario();

    $etranger = User::factory()->create(['role' => 'etudiant']);

    $this->actingAs($etranger)
        ->postJson("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/remettre")
        ->assertForbidden();
});

test('la remise est refusée si le document est verrouillé', function () {
    ['cours' => $cours, 'classeSection' => $cs, 'classe' => $classe, 'etudiant1' => $etudiant, 'typeProjet' => $typeProjet] = creerScenario();

    ProjetRecherche::create(['groupe_id' => $classe->id, 'type_projet_id' => $typeProjet->id, 'verrouille' => true]);

    $this->actingAs($etudiant)
        ->postJson("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/remettre")
        ->assertForbidden();
});

test('une deuxième remise est refusée sans remises multiples (paramètre TypeProjet)', function () {
    ['cours' => $cours, 'classeSection' => $cs, 'classe' => $classe, 'etudiant1' => $etudiant, 'typeProjet' => $typeProjet] = creerScenario();

    $typeProjet->update(['remises_multiples' => false]);

    ProjetRecherche::create([
        'groupe_id' => $classe->id,
        'type_projet_id' => $typeProjet->id,
        'remis_le' => now(),
    ]);

    $this->actingAs($etudiant)
        ->postJson("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/remettre")
        ->assertUnprocessable();
});

test('une deuxième remise est autorisée avec remises multiples (paramètre TypeProjet)', function () {
    ['cours' => $cours, 'classeSection' => $cs, 'classe' => $classe, 'etudiant1' => $etudiant, 'typeProjet' => $typeProjet] = creerScenario();

    $typeProjet->update(['remises_multiples' => true]);

    ProjetRecherche::create([
        'groupe_id' => $classe->id,
        'type_projet_id' => $typeProjet->id,
        'remis_le' => now()->subDay(),
    ]);

    $this->actingAs($etudiant)
        ->postJson("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/remettre")
        ->assertOk()
        ->assertJson(['message' => 'remis']);
});

// ─── Paramètres de remise — route supprimée (Sprint 10) ───────────────────────

test('la route parametres-remise est supprimée et retourne 404', function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classeSection' => $cs, 'classe' => $classe, 'typeProjet' => $typeProjet] = creerScenario();

    $this->actingAs($enseignant)
        ->patchJson("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/parametres-remise", [
            'date_remise' => '2026-04-15',
        ])
        ->assertNotFound();
});

// ─── Publication des notes ─────────────────────────────────────────────────────

test('un étudiant ne voit pas sa note si correction_visible est false', function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classeSection' => $cs, 'classe' => $classe, 'etudiant1' => $etudiant, 'typeProjet' => $typeProjet] = creerScenario();

    $projet = ProjetRecherche::create(['groupe_id' => $classe->id, 'type_projet_id' => $typeProjet->id, 'correction_visible' => false]);

    ProjetNote::create([
        'projet_id' => $projet->id,
        'user_id' => $etudiant->id,
        'critere' => 'normes_presentation',
        'note' => 3,
    ]);

    $this->actingAs($etudiant)
        ->get("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/edit")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('correctionVisible', false)
            ->where("noteFinaleParEtudiant.{$etudiant->id}", null)
        );
});

test('un étudiant voit sa note quand correction_visible est true', function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classeSection' => $cs, 'classe' => $classe, 'etudiant1' => $etudiant, 'typeProjet' => $typeProjet] = creerScenario();

    $projet = ProjetRecherche::create(['groupe_id' => $classe->id, 'type_projet_id' => $typeProjet->id, 'correction_visible' => true]);

    ProjetNote::create([
        'projet_id' => $projet->id,
        'user_id' => $etudiant->id,
        'critere' => 'normes_presentation',
        'note' => 4,
    ]);

    $this->actingAs($etudiant)
        ->get("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/edit")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('correctionVisible', true)
            ->where("noteFinaleParEtudiant.{$etudiant->id}", 100) // normes_presentation=4/4, autres critères = 4 par défaut → 100
        );
});

test("l'enseignant voit toujours les notes même si correction_visible est false", function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classeSection' => $cs, 'classe' => $classe, 'etudiant1' => $etudiant, 'typeProjet' => $typeProjet] = creerScenario();

    $projet = ProjetRecherche::create(['groupe_id' => $classe->id, 'type_projet_id' => $typeProjet->id, 'correction_visible' => false]);

    ProjetNote::create([
        'projet_id' => $projet->id,
        'user_id' => $etudiant->id,
        'critere' => 'normes_presentation',
        'note' => 2,
    ]);

    $this->actingAs($enseignant)
        ->get("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/edit")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where("noteFinaleParEtudiant.{$etudiant->id}", 95) // normes_presentation=2/4*10=5, autres critères = 4 par défaut → 95
        );
});

test('publier les corrections active correction_visible et le toggle le désactive', function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classeSection' => $cs, 'classe' => $classe, 'typeProjet' => $typeProjet] = creerScenario();

    // Premier appel : active
    $this->actingAs($enseignant)
        ->patchJson("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/correction-visible")
        ->assertJson(['correction_visible' => true]);

    // Second appel : désactive
    $this->actingAs($enseignant)
        ->patchJson("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/correction-visible")
        ->assertJson(['correction_visible' => false]);
});

// ─── Annotations : position et mot_annote ──────────────────────────────────────

test('upsertAnnotation persiste la position séquentielle et le mot annoté', function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classeSection' => $cs, 'classe' => $classe, 'typeProjet' => $typeProjet] = creerScenario();

    $section = TypeProjetSection::create(['type_projet_id' => $typeProjet->id, 'label' => 'Intro', 'ordre' => 1, 'type' => 'texte']);
    $champ = "section_{$section->id}";

    $uuid = Str::uuid()->toString();
    $html = '<p>Voici <mark data-comment-id="'.$uuid.'" data-annotation-type="commentaire">société</mark> moderne.</p>';

    $this->actingAs($enseignant)
        ->putJson("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/annotations", [
            'champ' => $champ,
            'commentaire_id' => $uuid,
            'contenu' => 'Attention à ce terme.',
            'type' => 'commentaire',
            'html' => $html,
        ])
        ->assertOk()
        ->assertJson(['message' => 'saved']);

    $this->assertDatabaseHas('projet_annotations', [
        'commentaire_id' => $uuid,
        'position' => 0,
        'mot_annote' => 'société',
    ]);
});

test('upsertAnnotation calcule la position correcte quand plusieurs marques existent', function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classeSection' => $cs, 'classe' => $classe, 'typeProjet' => $typeProjet] = creerScenario();

    $section = TypeProjetSection::create(['type_projet_id' => $typeProjet->id, 'label' => 'Intro', 'ordre' => 1, 'type' => 'texte']);
    $champ = "section_{$section->id}";

    $uuidA = Str::uuid()->toString();
    $uuidB = Str::uuid()->toString();
    $html = '<p><mark data-comment-id="'.$uuidA.'" data-annotation-type="commentaire">premier</mark> et '
          .'<mark data-comment-id="'.$uuidB.'" data-annotation-type="commentaire">second</mark>.</p>';

    $this->actingAs($enseignant)
        ->putJson("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/annotations", [
            'champ' => $champ,
            'commentaire_id' => $uuidB,
            'contenu' => 'Annotation sur le second mot.',
            'type' => 'commentaire',
            'html' => $html,
        ])
        ->assertOk();

    $this->assertDatabaseHas('projet_annotations', [
        'commentaire_id' => $uuidB,
        'position' => 1,
        'mot_annote' => 'second',
    ]);
});

test('les annotations sont triées par position dans show()', function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classeSection' => $cs, 'classe' => $classe, 'etudiant1' => $etudiant, 'typeProjet' => $typeProjet] = creerScenario();

    $uuidA = Str::uuid()->toString();
    $uuidB = Str::uuid()->toString();

    $projet = ProjetRecherche::create(['groupe_id' => $classe->id, 'type_projet_id' => $typeProjet->id]);

    ProjetAnnotation::create([
        'projet_id' => $projet->id,
        'champ' => 'introduction_amener',
        'commentaire_id' => $uuidB,
        'contenu' => 'Second mot',
        'type' => 'commentaire',
        'position' => 1,
        'user_id' => $enseignant->id,
    ]);

    ProjetAnnotation::create([
        'projet_id' => $projet->id,
        'champ' => 'introduction_amener',
        'commentaire_id' => $uuidA,
        'contenu' => 'Premier mot',
        'type' => 'commentaire',
        'position' => 0,
        'user_id' => $enseignant->id,
    ]);

    $this->actingAs($etudiant)
        ->get("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/edit")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('annotationsParChamp.introduction_amener.0.commentaire_id', $uuidA)
            ->where('annotationsParChamp.introduction_amener.1.commentaire_id', $uuidB)
        );
});

test('upsertAnnotation supprime les annotations orphelines du même champ', function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classeSection' => $cs, 'classe' => $classe, 'typeProjet' => $typeProjet] = creerScenario();

    $section = TypeProjetSection::create(['type_projet_id' => $typeProjet->id, 'label' => 'Intro', 'ordre' => 1, 'type' => 'texte']);
    $champ = "section_{$section->id}";

    $projet = ProjetRecherche::create(['groupe_id' => $classe->id, 'type_projet_id' => $typeProjet->id]);
    $uuidOrpheline = Str::uuid()->toString();
    $uuidNouvelle = Str::uuid()->toString();

    // Annotation existante sans marque dans le nouveau HTML (orpheline)
    ProjetAnnotation::create([
        'projet_id' => $projet->id,
        'champ' => $champ,
        'commentaire_id' => $uuidOrpheline,
        'contenu' => 'Annotation dont la marque a disparu.',
        'type' => 'commentaire',
        'user_id' => $enseignant->id,
    ]);

    $html = '<p><mark data-comment-id="'.$uuidNouvelle.'" data-annotation-type="commentaire">nouveau</mark></p>';

    $this->actingAs($enseignant)
        ->putJson("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/annotations", [
            'champ' => $champ,
            'commentaire_id' => $uuidNouvelle,
            'contenu' => 'Nouvelle annotation.',
            'type' => 'commentaire',
            'html' => $html,
        ])
        ->assertOk();

    $this->assertDatabaseMissing('projet_annotations', ['commentaire_id' => $uuidOrpheline]);
    $this->assertDatabaseHas('projet_annotations', ['commentaire_id' => $uuidNouvelle]);
});

test("la sauvegarde du contenu d'une section supprime les annotations orphelines", function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classeSection' => $cs, 'classe' => $classe, 'etudiant1' => $etudiant, 'typeProjet' => $typeProjet] = creerScenario();

    $section = TypeProjetSection::create(['type_projet_id' => $typeProjet->id, 'label' => 'Intro', 'ordre' => 1, 'type' => 'texte']);
    $champ = "section_{$section->id}";

    $projet = ProjetRecherche::create(['groupe_id' => $classe->id, 'type_projet_id' => $typeProjet->id]);
    $uuid = Str::uuid()->toString();

    ProjetAnnotation::create([
        'projet_id' => $projet->id,
        'champ' => $champ,
        'commentaire_id' => $uuid,
        'contenu' => 'Annotation sur un mot supprimé.',
        'type' => 'commentaire',
        'user_id' => $enseignant->id,
    ]);

    // L'étudiant sauvegarde le contenu de la section sans la marque (mot effacé)
    $this->actingAs($etudiant)
        ->putJson("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/sections/{$section->id}", [
            'contenu' => '<p>Texte sans marque.</p>',
        ])
        ->assertOk();

    $this->assertDatabaseMissing('projet_annotations', ['commentaire_id' => $uuid]);
});

test('destroyAnnotation ne supprime pas les autres annotations du même champ', function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classeSection' => $cs, 'classe' => $classe, 'typeProjet' => $typeProjet] = creerScenario();

    $section = TypeProjetSection::create(['type_projet_id' => $typeProjet->id, 'label' => 'Intro', 'ordre' => 1, 'type' => 'texte']);
    $champ = "section_{$section->id}";

    $projet = ProjetRecherche::create(['groupe_id' => $classe->id, 'type_projet_id' => $typeProjet->id]);

    $uuidA = Str::uuid()->toString();
    $uuidB = Str::uuid()->toString();

    $annotationA = ProjetAnnotation::create([
        'projet_id' => $projet->id,
        'champ' => $champ,
        'commentaire_id' => $uuidA,
        'contenu' => 'Première annotation.',
        'type' => 'commentaire',
        'user_id' => $enseignant->id,
    ]);

    ProjetAnnotation::create([
        'projet_id' => $projet->id,
        'champ' => $champ,
        'commentaire_id' => $uuidB,
        'contenu' => 'Deuxième annotation.',
        'type' => 'commentaire',
        'user_id' => $enseignant->id,
    ]);

    // L'enseignant supprime uniquement l'annotation A.
    // Le HTML envoyé ne contient plus la marque de A, mais conserve celle de B.
    $htmlApres = '<p><mark data-comment-id="'.$uuidB.'" data-annotation-type="commentaire">second</mark></p>';

    $this->actingAs($enseignant)
        ->deleteJson("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$classe->id}/projets/{$typeProjet->id}/annotations/{$annotationA->id}", [
            'champ' => $champ,
            'html' => $htmlApres,
        ])
        ->assertOk();

    // A est supprimée, B doit rester intacte.
    $this->assertDatabaseMissing('projet_annotations', ['commentaire_id' => $uuidA]);
    $this->assertDatabaseHas('projet_annotations', ['commentaire_id' => $uuidB]);
});
