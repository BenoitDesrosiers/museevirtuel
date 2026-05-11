<?php

use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetRecherche;
use App\Models\ProjetRenvoi;
use App\Models\TypeProjet;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Crée le scénario minimal pour tester aide_reference :
 * enseignant, cours, classe, groupe et un étudiant membre.
 *
 * @param  array<string, mixed>  $typeProjetOverrides
 * @return array{enseignant: User, typeProjet: TypeProjet, cours: Cours, cs: Classe, groupe: Groupe, etudiant: User}
 */
function creerScenarioAideRef(array $typeProjetOverrides = []): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    $typeProjet = TypeProjet::create(array_merge([
        'enseignant_id' => $enseignant->id,
        'nom' => 'Projet aide référence',
        'accessible' => true,
        'aide_reference' => false,
    ], $typeProjetOverrides));

    $cours = Cours::create([
        'nom_cours' => 'Histoire du Québec',
        'code' => '330-APA',
        'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);

    $etudiant = User::factory()->create(['role' => 'etudiant']);
    $cs = Classe::create(['cours_id' => $cours->id]);
    $cs->etudiants()->attach($etudiant->id);
    $groupe = Groupe::create(['classe_id' => $cs->id, 'created_by' => $etudiant->id]);
    $groupe->membres()->attach($etudiant->id);

    return compact('enseignant', 'typeProjet', 'cours', 'cs', 'groupe', 'etudiant');
}

// ─── TypeProjet store ─────────────────────────────────────────────────────────

it('peut créer un type de projet avec aide_reference activée', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = Cours::create([
        'nom_cours' => 'Cours test', 'code' => '330-T1', 'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);

    $this->actingAs($enseignant)
        ->postJson("/cours/{$cours->id}/types-projets", [
            'nom' => 'Projet avec aide',
            'aide_reference' => true,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('types_projets', [
        'cours_id' => $cours->id,
        'nom' => 'Projet avec aide',
        'aide_reference' => true,
    ]);
});

it('aide_reference est false par défaut à la création', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = Cours::create([
        'nom_cours' => 'Cours test', 'code' => '330-T1', 'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);

    $this->actingAs($enseignant)
        ->postJson("/cours/{$cours->id}/types-projets", ['nom' => 'Projet sans aide'])
        ->assertRedirect();

    $this->assertDatabaseHas('types_projets', [
        'cours_id' => $cours->id,
        'nom' => 'Projet sans aide',
        'aide_reference' => false,
    ]);
});

// ─── TypeProjet update ────────────────────────────────────────────────────────

it('peut activer aide_reference via update', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = Cours::create([
        'nom_cours' => 'Cours test', 'code' => '330-T1', 'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);
    $typeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id, 'cours_id' => $cours->id,
        'nom' => 'Projet', 'accessible' => false, 'aide_reference' => false,
    ]);

    $this->actingAs($enseignant)
        ->putJson("/cours/{$cours->id}/types-projets/{$typeProjet->id}", [
            'nom' => 'Projet', 'aide_reference' => true,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('types_projets', [
        'id' => $typeProjet->id, 'aide_reference' => true,
    ]);
});

it('peut désactiver aide_reference via update', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = Cours::create([
        'nom_cours' => 'Cours test', 'code' => '330-T1', 'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);
    $typeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id, 'cours_id' => $cours->id,
        'nom' => 'Projet', 'accessible' => false, 'aide_reference' => true,
    ]);

    $this->actingAs($enseignant)
        ->putJson("/cours/{$cours->id}/types-projets/{$typeProjet->id}", [
            'nom' => 'Projet', 'aide_reference' => false,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('types_projets', [
        'id' => $typeProjet->id, 'aide_reference' => false,
    ]);
});

it('update préserve aide_reference quand le paramètre est absent', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = Cours::create([
        'nom_cours' => 'Cours test', 'code' => '330-T1', 'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);
    $typeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id, 'cours_id' => $cours->id,
        'nom' => 'Projet', 'accessible' => false, 'aide_reference' => true,
    ]);

    // Aucun champ aide_reference dans la requête → doit conserver true
    $this->actingAs($enseignant)
        ->putJson("/cours/{$cours->id}/types-projets/{$typeProjet->id}", [
            'nom' => 'Projet renommé',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('types_projets', [
        'id' => $typeProjet->id,
        'nom' => 'Projet renommé',
        'aide_reference' => true,
    ]);
});

// ─── Page Show : prop aideReference ──────────────────────────────────────────

it('transmet aideReference=true à la vue Show du projet', function () {
    [
        'enseignant' => $enseignant,
        'typeProjet' => $typeProjet,
        'cours' => $cours,
        'cs' => $cs,
        'groupe' => $groupe,
        'etudiant' => $etudiant,
    ] = creerScenarioAideRef(['aide_reference' => true]);

    $this->actingAs($etudiant)
        ->get("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/edit")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('aideReference', true)
        );
});

it('transmet aideReference=false à la vue Show du projet', function () {
    [
        'enseignant' => $enseignant,
        'typeProjet' => $typeProjet,
        'cours' => $cours,
        'cs' => $cs,
        'groupe' => $groupe,
        'etudiant' => $etudiant,
    ] = creerScenarioAideRef(['aide_reference' => false]);

    $this->actingAs($etudiant)
        ->get("/cours/{$cours->id}/classes/{$cs->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/edit")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('aideReference', false)
        );
});

// ─── Modèle ───────────────────────────────────────────────────────────────────

it('cast aide_reference en booléen dans le modèle TypeProjet', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    $typeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id,
        'nom' => 'Test cast',
        'accessible' => false,
        'aide_reference' => true,
    ]);

    $typeProjet->refresh();

    expect($typeProjet->aide_reference)->toBe(true)
        ->and(gettype($typeProjet->aide_reference))->toBe('boolean');
});

// ─── storeRenvoi : type_reference et champs_reference ─────────────────────────

it('storeRenvoi persiste type_reference et champs_reference', function () {
    [
        'typeProjet' => $typeProjet,
        'cours' => $cours,
        'cs' => $cs,
        'groupe' => $groupe,
        'etudiant' => $etudiant,
    ] = creerScenarioAideRef(['aide_reference' => true]);

    $baseUrl = "/cours/{$cours->id}/classes/{$cs->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}";

    $champs = ['auteurs' => 'Smith, J.', 'annee' => '2023', 'titre' => 'Titre du livre', 'editeur' => 'Éditeur'];

    $response = $this->actingAs($etudiant)
        ->postJson("{$baseUrl}/renvois", [
            'contenu' => 'Smith, J. (2023). <em>Titre du livre</em>. Éditeur.',
            'type_reference' => 'livre',
            'champs_reference' => $champs,
        ]);

    $response->assertCreated()
        ->assertJsonPath('renvoi.type_reference', 'livre')
        ->assertJsonPath('renvoi.champs_reference.auteurs', 'Smith, J.');

    $this->assertDatabaseHas('projet_renvois', ['type_reference' => 'livre']);
});

it('storeRenvoi fonctionne sans type_reference ni champs_reference', function () {
    [
        'typeProjet' => $typeProjet,
        'cours' => $cours,
        'cs' => $cs,
        'groupe' => $groupe,
        'etudiant' => $etudiant,
    ] = creerScenarioAideRef();

    $baseUrl = "/cours/{$cours->id}/classes/{$cs->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}";

    $this->actingAs($etudiant)
        ->postJson("{$baseUrl}/renvois", ['contenu' => 'Référence sans modal.'])
        ->assertCreated()
        ->assertJsonPath('renvoi.type_reference', null)
        ->assertJsonPath('renvoi.champs_reference', null);
});

// ─── updateRenvoi : type_reference et champs_reference ────────────────────────

it('updateRenvoi met à jour les trois champs', function () {
    [
        'typeProjet' => $typeProjet,
        'cours' => $cours,
        'cs' => $cs,
        'groupe' => $groupe,
        'etudiant' => $etudiant,
    ] = creerScenarioAideRef(['aide_reference' => true]);

    $projet = ProjetRecherche::create([
        'groupe_id' => $groupe->id,
        'type_projet_id' => $typeProjet->id,
    ]);

    $renvoi = ProjetRenvoi::create([
        'projet_id' => $projet->id,
        'numero' => 1,
        'contenu' => 'Ancien contenu.',
        'type_reference' => 'livre',
        'champs_reference' => ['auteurs' => 'Ancien, A.', 'annee' => '2020', 'titre' => 'Ancien titre', 'editeur' => 'Ancienne maison'],
    ]);

    $baseUrl = "/cours/{$cours->id}/classes/{$cs->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}";
    $nouveauxChamps = ['auteur_organisme' => 'Gouvernement', 'annee' => '2024', 'titre_page' => 'Page', 'nom_site' => 'Site', 'url' => 'https://exemple.ca'];

    $this->actingAs($etudiant)
        ->patchJson("{$baseUrl}/renvois/{$renvoi->id}", [
            'contenu' => 'Nouveau contenu.',
            'type_reference' => 'site_internet',
            'champs_reference' => $nouveauxChamps,
        ])
        ->assertOk()
        ->assertJsonPath('message', 'saved');

    $renvoi->refresh();

    expect($renvoi->contenu)->toBe('Nouveau contenu.')
        ->and($renvoi->type_reference)->toBe('site_internet')
        ->and($renvoi->champs_reference['auteur_organisme'])->toBe('Gouvernement');
});

it('updateRenvoi retourne 404 si le renvoi n\'appartient pas au projet du groupe', function () {
    [
        'typeProjet' => $typeProjet,
        'cours' => $cours,
        'cs' => $cs,
        'groupe' => $groupe,
        'etudiant' => $etudiant,
    ] = creerScenarioAideRef(['aide_reference' => true]);

    // Second groupe avec son propre projet et renvoi — le premier groupe ne doit pas pouvoir y accéder
    $autreEtudiant = User::factory()->create(['role' => 'etudiant']);
    $cs->etudiants()->attach($autreEtudiant->id);
    $autreGroupe = Groupe::create(['classe_id' => $cs->id, 'created_by' => $autreEtudiant->id]);
    $autreGroupe->membres()->attach($autreEtudiant->id);

    $autreProjet = ProjetRecherche::create([
        'groupe_id' => $autreGroupe->id,
        'type_projet_id' => $typeProjet->id,
    ]);

    $renvoiAutreProjet = ProjetRenvoi::create([
        'projet_id' => $autreProjet->id,
        'numero' => 1,
        'contenu' => 'Renvoi d\'un autre projet.',
    ]);

    $baseUrl = "/cours/{$cours->id}/classes/{$cs->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}";

    // L'étudiant du groupe 1 tente de modifier le renvoi du groupe 2 (IDOR)
    $this->actingAs($etudiant)
        ->patchJson("{$baseUrl}/renvois/{$renvoiAutreProjet->id}", ['contenu' => 'Tentative IDOR.'])
        ->assertNotFound();
});
