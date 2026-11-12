<?php

use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\MuseeBloc;
use App\Models\MuseeMeta;
use App\Models\MuseeVideoSegment;
use App\Models\ProjetCritereCorrection;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use App\Models\TypeProjetCritere;
use App\Models\TypeProjetSection;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Projet musée complet : enseignant, étudiant membre, section, projet et métadonnées.
 *
 * @return array<string, mixed>
 */
function scenarioMuseeAudit(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $etudiant = User::factory()->create(['role' => 'etudiant', 'prenom' => 'Léa', 'nom' => 'Tremblay']);
    $cours = Cours::create(['nom_cours' => 'Musée', 'code' => '330-MAU', 'groupe' => '01', 'enseignant_id' => $enseignant->id]);
    $classe = Classe::create(['code' => 'A2026', 'cours_id' => $cours->id]);
    $groupe = Groupe::create(['classe_id' => $classe->id, 'created_by' => $etudiant->id]);
    $groupe->membres()->attach($etudiant->id);
    $typeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id,
        'cours_id' => $cours->id,
        'nom' => 'Musée audit',
        'type' => 'musee',
        'accessible' => true,
    ]);
    $section = TypeProjetSection::create(['type_projet_id' => $typeProjet->id, 'label' => 'Section', 'ordre' => 1, 'type' => 'texte']);
    $projet = ProjetRecherche::firstOrCreate(['groupe_id' => $groupe->id, 'type_projet_id' => $typeProjet->id]);
    MuseeMeta::firstOrCreate(['projet_recherche_id' => $projet->id], ['slug' => 'musee-audit-'.$projet->id]);

    return compact('enseignant', 'etudiant', 'cours', 'classe', 'groupe', 'typeProjet', 'section', 'projet');
}

// ─── B4 — Correction : groupe et individuelles ───────────────────────────────

test('la page de correction distingue la correction de groupe des corrections individuelles', function () {
    $s = scenarioMuseeAudit();
    $critere = TypeProjetCritere::create([
        'type_projet_id' => $s['typeProjet']->id,
        'type' => 'positif',
        'contenu_type' => 'texte',
        'pointage' => 10,
        'visible' => true,
        'ordre' => 1,
    ]);
    ProjetCritereCorrection::create(['projet_id' => $s['projet']->id, 'critere_id' => $critere->id, 'user_id' => null, 'points' => 8, 'verifie' => true]);
    ProjetCritereCorrection::create(['projet_id' => $s['projet']->id, 'critere_id' => $critere->id, 'user_id' => $s['etudiant']->id, 'points' => 5, 'verifie' => true]);

    $this->actingAs($s['enseignant'])
        ->get("/cours/{$s['cours']->id}/classes/{$s['classe']->id}/groupes/{$s['groupe']->id}/projets/{$s['typeProjet']->id}/musee/correction")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Musee/Correction')
            ->where('criteres.0.correction.points', '8.00')
            ->has('criteres.0.correctionsIndividuelles', 1)
            ->where('criteres.0.correctionsIndividuelles.0.points', '5.00')
            ->where('criteres.0.correctionsIndividuelles.0.etudiant', 'Léa Tremblay')
        );
});

// ─── B5 — File d'approbation ─────────────────────────────────────────────────

test('la file d\'approbation affiche le code du groupe et les métadonnées sans requête par projet', function () {
    $s = scenarioMuseeAudit();
    MuseeMeta::where('projet_recherche_id', $s['projet']->id)->update(['entete_titre' => 'Mon musée']);

    $this->actingAs($s['enseignant'])
        ->get("/cours/{$s['cours']->id}/types-projets/{$s['typeProjet']->id}/musee-approbation")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Musee/Approbation/Index')
            ->where('projets.0.groupe.nom', $s['groupe']->code)
            ->where('projets.0.meta.entete_titre', 'Mon musée')
        );
});

// ─── B6 — Segment vidéo ──────────────────────────────────────────────────────

/**
 * Crée un bloc vidéo et un segment 10 s → 20 s, et retourne l'URL de modification du segment.
 *
 * @param  array<string, mixed>  $s
 */
function segmentMuseeAudit(array $s): string
{
    $bloc = MuseeBloc::create([
        'projet_recherche_id' => $s['projet']->id,
        'section_id' => $s['section']->id,
        'type' => MuseeBloc::TYPE_VIDEO,
        'contenu' => [],
        'ordre' => 1,
    ]);
    $segment = MuseeVideoSegment::create([
        'bloc_id' => $bloc->id,
        'section_id' => $s['section']->id,
        'debut_secondes' => 10,
        'fin_secondes' => 20,
        'label' => 'Extrait',
    ]);

    return "/cours/{$s['cours']->id}/classes/{$s['classe']->id}/groupes/{$s['groupe']->id}/projets/{$s['typeProjet']->id}/sections/{$s['section']->id}/blocs/{$bloc->id}/segments/{$segment->id}";
}

test('la modification d\'un segment refuse une fin placée avant le début', function (array $donnees) {
    $s = scenarioMuseeAudit();
    $url = segmentMuseeAudit($s);

    $this->actingAs($s['etudiant'])->patch($url, $donnees)->assertSessionHasErrors('fin_secondes');

    expect(MuseeVideoSegment::first()->only('debut_secondes', 'fin_secondes'))
        ->toBe(['debut_secondes' => 10, 'fin_secondes' => 20]);
})->with([
    'fin seule avant le début enregistré' => [['fin_secondes' => 5]],
    'début seul après la fin enregistrée' => [['debut_secondes' => 25]],
    'les deux bornes inversées' => [['debut_secondes' => 30, 'fin_secondes' => 15]],
    'fin égale au début' => [['debut_secondes' => 12, 'fin_secondes' => 12]],
]);

test('la modification d\'un segment accepte des bornes cohérentes', function () {
    $s = scenarioMuseeAudit();
    $url = segmentMuseeAudit($s);

    $this->actingAs($s['etudiant'])->patch($url, ['fin_secondes' => 40])->assertSessionHasNoErrors();

    expect(MuseeVideoSegment::first()->fin_secondes)->toBe(40);
});
