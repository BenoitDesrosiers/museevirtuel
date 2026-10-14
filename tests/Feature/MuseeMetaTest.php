<?php

use App\Models\Classe;
use App\Models\Cours;
use App\Models\EpoqueHistorique;
use App\Models\Groupe;
use App\Models\MuseeMeta;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Crée l'arborescence cours → classe → groupe → typeProjet musée
 * et retourne les 4 entités.
 *
 * @return array{Cours, Classe, Groupe, TypeProjet}
 */
function creerContexteMusee(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $etudiant = User::factory()->create(['role' => 'etudiant']);

    $cours = Cours::create([
        'nom_cours' => 'Cours musée meta test',
        'code' => '330-MM',
        'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);

    $classe = Classe::create([
        'code' => 'H2025',
        'cours_id' => $cours->id,
    ]);

    $groupe = Groupe::create([
        'numero' => 1,
        'classe_id' => $classe->id,
        'created_by' => $etudiant->id,
    ]);

    $groupe->membres()->attach($etudiant->id);

    $typeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id,
        'cours_id' => $cours->id,
        'nom' => 'Musée test',
        'type' => 'musee',
        'accessible' => true,
    ]);

    return [$cours, $classe, $groupe, $typeProjet, $enseignant, $etudiant];
}

// ─── Observer — auto-création de MuseeMeta ────────────────────────────────────

test("l'observer crée un MuseeMeta automatiquement à la création d'un ProjetRecherche musée", function () {
    [$cours, $classe, $groupe, $typeProjet, $enseignant, $etudiant] = creerContexteMusee();

    $projet = ProjetRecherche::firstOrCreate([
        'groupe_id' => $groupe->id,
        'type_projet_id' => $typeProjet->id,
    ]);

    expect(MuseeMeta::where('projet_recherche_id', $projet->id)->exists())->toBeTrue();
});

test("l'observer ne crée pas de MuseeMeta pour un ProjetRecherche standard", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = Cours::create(['nom_cours' => 'Standard', 'code' => '330-ST', 'groupe' => '01', 'enseignant_id' => $enseignant->id]);
    $classe = Classe::create(['code' => 'H2025', 'cours_id' => $cours->id]);
    $etudiant2 = User::factory()->create(['role' => 'etudiant']);
    $groupe = Groupe::create(['classe_id' => $classe->id, 'created_by' => $etudiant2->id]);
    $typeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id,
        'cours_id' => $cours->id,
        'nom' => 'Projet standard',
        'type' => 'standard',
        'accessible' => true,
    ]);

    $projet = ProjetRecherche::firstOrCreate([
        'groupe_id' => $groupe->id,
        'type_projet_id' => $typeProjet->id,
    ]);

    expect(MuseeMeta::where('projet_recherche_id', $projet->id)->exists())->toBeFalse();
});

test('le slug généré automatiquement est unique', function () {
    [$cours, $classe, $groupe, $typeProjet, $enseignant, $etudiant] = creerContexteMusee();

    $projet = ProjetRecherche::firstOrCreate([
        'groupe_id' => $groupe->id,
        'type_projet_id' => $typeProjet->id,
    ]);

    $meta = MuseeMeta::where('projet_recherche_id', $projet->id)->first();
    expect($meta->slug)->not->toBeEmpty();
    // Le slug doit être unique en base
    expect(MuseeMeta::where('slug', $meta->slug)->count())->toBe(1);
});

// ─── Projet\ProjetRechercheController::show — rendu Musee/Show ──────────────────────

test("l'accès au projet musée rend la page Musee/Show", function () {
    [$cours, $classe, $groupe, $typeProjet, $enseignant, $etudiant] = creerContexteMusee();

    $this->actingAs($etudiant)
        ->get("/cours/{$cours->id}/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/edit")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Musee/Show')
            ->has('meta')
            ->has('template')
            ->has('epoques')
            ->has('thematiques')
            ->has('regionsAdministratives')
        );
});

test("l'accès au projet musée crée automatiquement le ProjetRecherche et la MuseeMeta", function () {
    [$cours, $classe, $groupe, $typeProjet, $enseignant, $etudiant] = creerContexteMusee();

    expect(ProjetRecherche::where('groupe_id', $groupe->id)->exists())->toBeFalse();

    $this->actingAs($etudiant)
        ->get("/cours/{$cours->id}/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/edit")
        ->assertOk();

    $projet = ProjetRecherche::where('groupe_id', $groupe->id)->first();
    expect($projet)->not->toBeNull();
    expect(MuseeMeta::where('projet_recherche_id', $projet->id)->exists())->toBeTrue();
});

// ─── MuseeMetaController::update ─────────────────────────────────────────────

test('un membre peut mettre à jour les métadonnées du projet musée', function () {
    [$cours, $classe, $groupe, $typeProjet, $enseignant, $etudiant] = creerContexteMusee();

    $projet = ProjetRecherche::firstOrCreate([
        'groupe_id' => $groupe->id,
        'type_projet_id' => $typeProjet->id,
    ]);

    $epoque = EpoqueHistorique::create(['nom' => 'Nouvelle-France', 'ordre' => 1]);

    $this->actingAs($etudiant)
        ->patch("/cours/{$cours->id}/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/musee-meta", [
            'intro_texte' => 'Un texte d\'introduction.',
            'epoque_historique_id' => $epoque->id,
        ])
        ->assertRedirect();

    $meta = MuseeMeta::where('projet_recherche_id', $projet->id)->first();
    expect($meta->intro_texte)->toBe("Un texte d'introduction.");
    expect($meta->epoque_historique_id)->toBe($epoque->id);
});

test('un non-membre ne peut pas mettre à jour les métadonnées (403)', function () {
    [$cours, $classe, $groupe, $typeProjet, $enseignant, $etudiant] = creerContexteMusee();
    $intrus = User::factory()->create(['role' => 'etudiant']);

    ProjetRecherche::firstOrCreate([
        'groupe_id' => $groupe->id,
        'type_projet_id' => $typeProjet->id,
    ]);

    $this->actingAs($intrus)
        ->patch("/cours/{$cours->id}/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/musee-meta", [
            'intro_texte' => 'Tentative IDOR',
        ])
        ->assertForbidden();
});

test("l'update des métadonnées rejette une époque inexistante", function () {
    [$cours, $classe, $groupe, $typeProjet, $enseignant, $etudiant] = creerContexteMusee();

    ProjetRecherche::firstOrCreate([
        'groupe_id' => $groupe->id,
        'type_projet_id' => $typeProjet->id,
    ]);

    $this->actingAs($etudiant)
        ->patch("/cours/{$cours->id}/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/musee-meta", [
            'epoque_historique_id' => 9999,
        ])
        ->assertSessionHasErrors('epoque_historique_id');
});

// ─── MuseeMetaController::updateEntete ───────────────────────────────────────

test("un membre peut mettre à jour l'en-tête du projet musée", function () {
    [$cours, $classe, $groupe, $typeProjet, $enseignant, $etudiant] = creerContexteMusee();

    $projet = ProjetRecherche::firstOrCreate([
        'groupe_id' => $groupe->id,
        'type_projet_id' => $typeProjet->id,
    ]);

    $this->actingAs($etudiant)
        ->post("/cours/{$cours->id}/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/musee-meta/entete", [
            'entete_titre' => 'La Nouvelle-France',
            'entete_sous_titre' => 'Groupe 1',
            'entete_overlay_couleur' => '#000000',
        ])
        ->assertRedirect();

    $meta = MuseeMeta::where('projet_recherche_id', $projet->id)->first();
    expect($meta->entete_titre)->toBe('La Nouvelle-France');
    expect($meta->entete_sous_titre)->toBe('Groupe 1');
    expect($meta->entete_overlay_couleur)->toBe('#000000');
});

test("l'en-tête rejette une couleur d'overlay au format invalide", function () {
    [$cours, $classe, $groupe, $typeProjet, $enseignant, $etudiant] = creerContexteMusee();

    ProjetRecherche::firstOrCreate([
        'groupe_id' => $groupe->id,
        'type_projet_id' => $typeProjet->id,
    ]);

    $this->actingAs($etudiant)
        ->post("/cours/{$cours->id}/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/musee-meta/entete", [
            'entete_titre' => 'Titre',
            'entete_overlay_couleur' => 'rouge',
        ])
        ->assertSessionHasErrors('entete_overlay_couleur');
});

test("l'accès à la route musee-meta/entete est refusé pour un TypeProjet standard (404)", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $etudiant = User::factory()->create(['role' => 'etudiant']);
    $cours = Cours::create(['nom_cours' => 'Test', 'code' => '330-TS', 'groupe' => '01', 'enseignant_id' => $enseignant->id]);
    $classe = Classe::create(['code' => 'H2025', 'cours_id' => $cours->id]);
    $groupe = Groupe::create(['classe_id' => $classe->id, 'created_by' => $etudiant->id]);
    $groupe->membres()->attach($etudiant->id);
    $typeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id,
        'cours_id' => $cours->id,
        'nom' => 'Standard',
        'type' => 'standard',
        'accessible' => true,
    ]);

    $this->actingAs($etudiant)
        ->post("/cours/{$cours->id}/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/musee-meta/entete", [
            'entete_titre' => 'Test',
        ])
        ->assertNotFound();
});
