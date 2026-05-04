<?php

use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetRecherche;
use App\Models\ProjetSchemaVisuel;
use App\Models\TypeProjet;
use App\Models\TypeProjetSection;
use App\Models\User;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Crée le scénario minimal : enseignant, cours, classe, groupe, membre, typeProjet, section schema_visuel, projet.
 *
 * @return array{enseignant: User, cours: Cours, classe: Classe, groupe: Groupe, membre: User, typeProjet: TypeProjet, section: TypeProjetSection, projet: ProjetRecherche}
 */
function creerScenarioSchema(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    $cours = Cours::create([
        'nom_cours' => 'Cours Schéma Visuel',
        'code' => '330-SCH',
        'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);

    $classe = Classe::create(['cours_id' => $cours->id]);

    $membre = User::factory()->create(['role' => 'etudiant']);
    $classe->etudiants()->attach($membre->id);

    $groupe = Groupe::create([
        'classe_id' => $classe->id,
        'created_by' => $membre->id,
    ]);
    $groupe->membres()->attach($membre->id);

    $typeProjet = TypeProjet::create([
        'cours_id' => $cours->id,
        'enseignant_id' => $enseignant->id,
        'nom' => 'Schéma visuel test',
        'accessible' => true,
    ]);

    $section = TypeProjetSection::create([
        'type_projet_id' => $typeProjet->id,
        'label' => 'Schéma',
        'type' => 'schema_visuel',
        'ordre' => 1,
    ]);

    $projet = ProjetRecherche::create([
        'groupe_id' => $groupe->id,
        'type_projet_id' => $typeProjet->id,
        'titre_projet' => 'Test schéma visuel',
    ]);

    return compact('enseignant', 'cours', 'classe', 'groupe', 'membre', 'typeProjet', 'section', 'projet');
}

/** URL PUT pour le schéma d'une section. */
function urlSchema(array $s): string
{
    $p = $s;

    return "/cours/{$p['cours']->id}/classes/{$p['classe']->id}/groupes/{$p['groupe']->id}/projets/{$p['typeProjet']->id}/sections/{$p['section']->id}/schema";
}

/** Contenu de schéma valide minimal. */
function contenuValide(): array
{
    return [
        'image_centrale' => null,
        'zones' => [
            'causes' => [
                ['id' => 'uuid-1', 'texte' => 'Cause A', 'image' => null],
            ],
            'activites' => [],
            'consequences' => [],
        ],
    ];
}

// ─── update() ─────────────────────────────────────────────────────────────────

test('un membre du groupe peut créer un schéma visuel', function () {
    $s = creerScenarioSchema();

    $this->actingAs($s['membre'])
        ->put(urlSchema($s), ['contenu' => contenuValide()])
        ->assertRedirect();

    $this->assertDatabaseHas('projet_schema_visuels', [
        'projet_id' => $s['projet']->id,
        'section_id' => $s['section']->id,
    ]);
});

test('le schéma est mis à jour (upsert) si une entrée existe déjà', function () {
    $s = creerScenarioSchema();

    ProjetSchemaVisuel::create([
        'projet_id' => $s['projet']->id,
        'section_id' => $s['section']->id,
        'contenu' => ProjetSchemaVisuel::contenuVide(),
    ]);

    $nouveau = contenuValide();
    $nouveau['zones']['activites'][] = ['id' => 'uuid-2', 'texte' => 'Activité B', 'image' => null];

    $this->actingAs($s['membre'])
        ->put(urlSchema($s), ['contenu' => $nouveau])
        ->assertRedirect();

    $schema = ProjetSchemaVisuel::where('projet_id', $s['projet']->id)->first();
    expect($schema->contenu['zones']['activites'])->toHaveCount(1);
});

test('un non-membre reçoit 403', function () {
    $s = creerScenarioSchema();
    $etranger = User::factory()->create(['role' => 'etudiant']);

    $this->actingAs($etranger)
        ->put(urlSchema($s), ['contenu' => contenuValide()])
        ->assertForbidden();
});

test('un projet verrouillé bloque la sauvegarde du schéma', function () {
    $s = creerScenarioSchema();

    $s['projet']->update(['verrouille' => true]);

    $this->actingAs($s['membre'])
        ->put(urlSchema($s), ['contenu' => contenuValide()])
        ->assertForbidden();
});

test('la validation rejette un contenu sans zones', function () {
    $s = creerScenarioSchema();

    $this->actingAs($s['membre'])
        ->put(urlSchema($s), ['contenu' => ['image_centrale' => null]])
        ->assertSessionHasErrors('contenu.zones');
});

test('l\'enseignant peut aussi sauvegarder le schéma de son cours', function () {
    $s = creerScenarioSchema();

    $this->actingAs($s['enseignant'])
        ->put(urlSchema($s), ['contenu' => contenuValide()])
        ->assertRedirect();

    $this->assertDatabaseHas('projet_schema_visuels', [
        'projet_id' => $s['projet']->id,
    ]);
});

test('IDOR : section appartenant à un autre typeProjet retourne 404', function () {
    $s = creerScenarioSchema();

    // Autre TypeProjet du même cours
    $autreTypeProjet = TypeProjet::create([
        'cours_id' => $s['cours']->id,
        'enseignant_id' => $s['enseignant']->id,
        'nom' => 'Autre type',
        'accessible' => true,
    ]);
    $autreSection = TypeProjetSection::create([
        'type_projet_id' => $autreTypeProjet->id,
        'label' => 'Autre section',
        'type' => 'schema_visuel',
        'ordre' => 1,
    ]);

    // On envoie la section de l'autre type dans l'URL du type original → 404
    $url = "/cours/{$s['cours']->id}/classes/{$s['classe']->id}/groupes/{$s['groupe']->id}/projets/{$s['typeProjet']->id}/sections/{$autreSection->id}/schema";

    $this->actingAs($s['membre'])
        ->put($url, ['contenu' => contenuValide()])
        ->assertNotFound();
});
