<?php

use App\Models\Classe;
use App\Models\Cours;
use App\Models\EntrevueConcept;
use App\Models\EntrevueLigne;
use App\Models\Groupe;
use App\Models\ProjetConclusion;
use App\Models\ProjetRecherche;
use App\Models\ProjetRenvoi;
use App\Models\ProjetSectionContenu;
use App\Models\ProjetSectionParagraphe;
use App\Models\TypeProjet;
use App\Models\TypeProjetSection;
use App\Models\User;

/**
 * Crée un cours, une classe et un groupe de deux étudiants.
 *
 * @return array{enseignant: User, cours: Cours, classe: Classe, groupe: Groupe, e1: User, e2: User}
 */
function creerGroupePagesProjet(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = Cours::create(['nom_cours' => 'Histoire', 'code' => '330-PP', 'groupe' => '01', 'enseignant_id' => $enseignant->id]);
    $e1 = User::factory()->create(['role' => 'etudiant', 'prenom' => 'Ana', 'nom' => 'Roy']);
    $e2 = User::factory()->create(['role' => 'etudiant', 'prenom' => 'Léo', 'nom' => 'Gay']);

    $classe = Classe::create(['cours_id' => $cours->id]);
    $classe->etudiants()->attach([$e1->id, $e2->id]);

    $groupe = Groupe::create(['classe_id' => $classe->id, 'created_by' => $e1->id]);
    $groupe->membres()->attach([$e1->id, $e2->id]);

    return compact('enseignant', 'cours', 'classe', 'groupe', 'e1', 'e2');
}

// ─── Index ────────────────────────────────────────────────────────────────────

test("l'index des projets donne une carte par type de projet avec l'état des conclusions", function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe, 'e1' => $e1, 'e2' => $e2] = creerGroupePagesProjet();

    $commence = TypeProjet::create(['enseignant_id' => $enseignant->id, 'cours_id' => $cours->id, 'nom' => 'Commencé', 'description' => 'Desc', 'accessible' => true]);
    $nonCommence = TypeProjet::create(['enseignant_id' => $enseignant->id, 'cours_id' => $cours->id, 'nom' => 'Pas commencé', 'accessible' => true]);
    TypeProjet::create(['enseignant_id' => $enseignant->id, 'cours_id' => $cours->id, 'nom' => 'Caché', 'accessible' => false]);

    $projet = ProjetRecherche::create(['groupe_id' => $groupe->id, 'type_projet_id' => $commence->id, 'titre_projet' => 'Mon titre']);
    ProjetConclusion::create(['projet_id' => $projet->id, 'user_id' => $e1->id, 'contenu' => '<p>Rédigée</p>']);
    ProjetConclusion::create(['projet_id' => $projet->id, 'user_id' => $e2->id, 'contenu' => '<p> </p>']);

    $this->actingAs($e1)
        ->get("/cours/{$cours->id}/classes/{$classe->id}/groupes/{$groupe->id}/projets")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Projets/Index')
            ->where('groupe', $groupe->fresh()->only('id', 'code', 'classe_id'))
            ->where('classe', $classe->fresh()->only('id', 'code', 'cours_id'))
            ->where('cours', $cours->only('id', 'nom_cours', 'code', 'groupe'))
            ->where('estEnseignant', false)
            // Le type non accessible est masqué à l'étudiant
            ->has('projets', 2)
            ->where('projets.0.typeProjet', ['id' => $commence->id, 'nom' => 'Commencé', 'description' => 'Desc', 'accessible' => true, 'type' => 'standard'])
            ->where('projets.0.projet.id', $projet->id)
            ->where('projets.0.projet.titre_projet', 'Mon titre')
            ->where('projets.0.projet.completion', $projet->fresh()->completion())
            ->where('projets.0.projet.statut', $projet->fresh()->statutActuel()->value)
            ->where('projets.0.projet.statut_publication', null)
            ->where('projets.0.statut', $projet->fresh()->statutActuel()->value)
            ->where('projets.0.conclusions', [
                ['etudiant' => $e1->only('id', 'prenom', 'nom'), 'a_redige' => true],
                ['etudiant' => $e2->only('id', 'prenom', 'nom'), 'a_redige' => false],
            ])
            ->where('projets.1.typeProjet.id', $nonCommence->id)
            ->where('projets.1.projet', null)
            ->where('projets.1.conclusions.0.a_redige', false)
        );
});

test("l'index montre à l'enseignant les types de projets non accessibles", function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe] = creerGroupePagesProjet();
    TypeProjet::create(['enseignant_id' => $enseignant->id, 'cours_id' => $cours->id, 'nom' => 'Caché', 'accessible' => false]);

    $this->actingAs($enseignant)
        ->get("/cours/{$cours->id}/classes/{$classe->id}/groupes/{$groupe->id}/projets")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('estEnseignant', true)
            ->has('projets', 1)
            ->where('projets.0.typeProjet.nom', 'Caché')
        );
});

test("l'index donne le statut de publication des projets musée", function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe, 'e1' => $e1] = creerGroupePagesProjet();
    $musee = TypeProjet::create(['enseignant_id' => $enseignant->id, 'cours_id' => $cours->id, 'nom' => 'Expo', 'type' => 'musee', 'accessible' => true]);
    ProjetRecherche::create(['groupe_id' => $groupe->id, 'type_projet_id' => $musee->id]);

    $this->actingAs($e1)
        ->get("/cours/{$cours->id}/classes/{$classe->id}/groupes/{$groupe->id}/projets")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('projets.0.typeProjet.type', 'musee')
            ->where('projets.0.projet.statut_publication', 'brouillon')
        );
});

// ─── Aperçu ───────────────────────────────────────────────────────────────────

test("l'aperçu nettoie les marques d'annotation et filtre les conclusions vides", function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe, 'e1' => $e1, 'e2' => $e2] = creerGroupePagesProjet();
    $tp = TypeProjet::create(['enseignant_id' => $enseignant->id, 'cours_id' => $cours->id, 'nom' => 'TP', 'accessible' => true]);

    $texte = TypeProjetSection::create(['type_projet_id' => $tp->id, 'label' => 'Intro', 'description' => 'D', 'ordre' => 1, 'type' => 'texte']);
    $para = TypeProjetSection::create(['type_projet_id' => $tp->id, 'label' => 'Dév', 'ordre' => 2, 'type' => 'paragraphes']);
    $indiv = TypeProjetSection::create(['type_projet_id' => $tp->id, 'label' => 'Concl', 'ordre' => 3, 'type' => 'individuel']);
    $entrevue = TypeProjetSection::create(['type_projet_id' => $tp->id, 'label' => 'Entrevue', 'ordre' => 4, 'type' => 'entrevue']);

    $projet = ProjetRecherche::create(['groupe_id' => $groupe->id, 'type_projet_id' => $tp->id, 'titre_projet' => 'Titre']);
    $marque = '<p>Avant <mark data-comment-id="7">marqué</mark> après</p>';
    ProjetSectionContenu::create(['projet_id' => $projet->id, 'section_id' => $texte->id, 'contenu' => $marque]);
    $p = ProjetSectionParagraphe::create(['projet_id' => $projet->id, 'section_id' => $para->id, 'ordre' => 1, 'titre' => 'P1', 'contenu' => $marque]);
    ProjetConclusion::create(['projet_id' => $projet->id, 'user_id' => $e1->id, 'section_id' => $indiv->id, 'contenu' => $marque]);
    ProjetConclusion::create(['projet_id' => $projet->id, 'user_id' => $e2->id, 'section_id' => $indiv->id, 'contenu' => '<p></p>']);
    $concept = EntrevueConcept::create(['projet_id' => $projet->id, 'section_id' => $entrevue->id, 'label' => 'Mémoire', 'ordre' => 1]);
    $ligne = EntrevueLigne::create(['concept_id' => $concept->id, 'dimension' => 'Dim', 'indicateur' => 'Ind', 'questions' => 'Q?', 'ordre' => 1]);
    $renvoi = ProjetRenvoi::create(['projet_id' => $projet->id, 'numero' => 1, 'contenu' => 'Source']);

    $propre = '<p>Avant marqué après</p>';

    $this->actingAs($e1)
        ->get("/cours/{$cours->id}/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$tp->id}/apercu")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Projets/Apercu')
            ->where('groupe', $groupe->fresh()->only('id', 'numero', 'classe_id'))
            ->where('typeProjet', ['id' => $tp->id, 'nom' => 'TP'])
            ->where('thematiques', [])
            ->where('membres', [$e1->only('id', 'prenom', 'nom'), $e2->only('id', 'prenom', 'nom')])
            ->where('projet', ['id' => $projet->id, 'titre_projet' => 'Titre'])
            ->where('estEnseignant', false)
            ->where('renvois', [['id' => $renvoi->id, 'numero' => 1, 'contenu' => 'Source']])
            ->has('sections', 4)
            ->where('sections.0', [
                'id' => $texte->id, 'label' => 'Intro', 'description' => 'D', 'ordre' => 1, 'type' => 'texte',
                'contenu' => $propre, 'paragraphes' => null, 'conclusionsParMembre' => null, 'concepts' => null,
            ])
            ->where('sections.1.contenu', null)
            ->where('sections.1.paragraphes', [['id' => $p->id, 'ordre' => 1, 'titre' => 'P1', 'contenu' => $propre]])
            ->where('sections.2.paragraphes', null)
            ->where('sections.2.conclusionsParMembre', [['userId' => $e1->id, 'contenu' => $propre]])
            ->where('sections.3.conclusionsParMembre', null)
            ->where('sections.3.concepts', [[
                'id' => $concept->id, 'label' => 'Mémoire', 'ordre' => 1,
                'lignes' => [['id' => $ligne->id, 'ordre' => 1, 'dimension' => 'Dim', 'indicateur' => 'Ind', 'questions' => 'Q?']],
            ]])
        );
});

test("l'aperçu d'un projet pas encore créé est vide", function () {
    ['enseignant' => $enseignant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe] = creerGroupePagesProjet();
    $tp = TypeProjet::create(['enseignant_id' => $enseignant->id, 'cours_id' => $cours->id, 'nom' => 'TP', 'accessible' => true]);

    $this->actingAs($enseignant)
        ->get("/cours/{$cours->id}/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$tp->id}/apercu")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('estEnseignant', true)
            ->where('projet', null)
            ->where('sections', [])
            ->where('renvois', [])
        );

    expect(ProjetRecherche::count())->toBe(0);
});
