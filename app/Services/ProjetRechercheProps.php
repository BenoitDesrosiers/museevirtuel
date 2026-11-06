<?php

namespace App\Services;

use App\Models\Classe;
use App\Models\ConsentementVideo;
use App\Models\Cours;
use App\Models\EntrevueConcept;
use App\Models\Groupe;
use App\Models\GroupeTache;
use App\Models\ProjetAnnotation;
use App\Models\ProjetCommentaire;
use App\Models\ProjetConclusion;
use App\Models\ProjetCritereEtudiantCoche;
use App\Models\ProjetRecherche;
use App\Models\ProjetRenvoi;
use App\Models\ProjetSchemaVisuel;
use App\Models\ProjetSectionMedia;
use App\Models\ProjetVoteRemise;
use App\Models\TypeProjet;
use App\Models\TypeProjetSection;
use App\Models\TypeProjetTache;
use App\Models\User;
use Illuminate\Support\Collection;
use Inertia\Inertia;

/**
 * Construit les props Inertia de l'éditeur de projet de recherche.
 *
 * Sorti du contrôleur pour que show() se limite à l'autorisation et au rendu,
 * et pour partager la construction des sections avec l'aperçu.
 */
class ProjetRechercheProps
{
    /**
     * Retourne les props de la page Projets/Show pour l'utilisateur donné.
     *
     * Charge les relations du projet en une passe (évite le N+1) et filtre
     * annotations, corrections et critères selon le rôle.
     *
     * @return array<string, mixed>
     */
    public function pourEditeur(Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, ProjetRecherche $projet, User $user, bool $estEnseignant): array
    {
        // Précharger en une seule requête chacune des relations — évite le N+1
        $projet->load(['conclusions', 'commentaires', 'annotations', 'developpements', 'votes', 'typeProjet.sections.questionsBanque', 'typeProjet.sections.criteres', 'typeProjet.criteresGlobaux', 'typeProjet.taches', 'sectionContenus', 'sectionParagraphes', 'entrevueConcepts.lignes', 'sectionMedias', 'questionsChoisies', 'schemaVisuels', 'renvois.commentaires', 'critereCorrections']);

        // État des tâches pour ce groupe — groupé par tache_id pour O(1) dans construireSections
        $tacheIds = $typeProjet->taches()->pluck('id');
        $groupeTachesParTache = $tacheIds->isNotEmpty()
            ? GroupeTache::where('groupe_id', $groupe->id)
                ->whereIn('tache_id', $tacheIds)
                ->with('assigneA:id,prenom,nom')
                ->get()
                ->keyBy('tache_id')
            : collect();

        $conclusionsParMembre = $projet->conclusions->keyBy('user_id');

        $conclusions = $groupe->membres->map(function (User $membre) use ($conclusionsParMembre): array {
            $conclusion = $conclusionsParMembre->get($membre->id);

            return [
                'etudiant' => $membre->only('id', 'prenom', 'nom'),
                'contenu' => $conclusion?->contenu,
            ];
        });

        // Commentaires indexés par champ pour un accès O(1) côté Vue
        $commentaires = $projet->commentaires->keyBy('champ')->map(fn (ProjetCommentaire $c) => [
            'id' => $c->id,
            'contenu' => $c->contenu,
        ]);

        // Pour les étudiants, masquer les annotations si correction_visible = false
        $annotationsFiltrees = $estEnseignant
            ? $projet->annotations
            : $projet->annotations->when(! $projet->correction_visible, fn ($coll) => $coll->whereNull('id'));

        // Annotations inline indexées par champ, triées par la position persistée en base.
        $annotationsParChamp = $annotationsFiltrees
            ->groupBy('champ')
            ->map(function ($annotations) {
                return $annotations
                    ->sortBy(fn (ProjetAnnotation $a): int => $a->position ?? PHP_INT_MAX)
                    ->map(fn (ProjetAnnotation $a) => [
                        'id' => $a->id,
                        'commentaire_id' => $a->commentaire_id,
                        'contenu' => $a->contenu,
                        'points_malus' => $a->points_malus !== null ? (float) $a->points_malus : null,
                        'cible_user_id' => $a->cible_user_id,
                        'annotation_type' => $a->annotation_type ?? 'commentaire',
                        'user_id' => $a->user_id,
                    ])
                    ->values();
            });

        $estMembre = ! $estEnseignant && $groupe->membres->contains('id', $user->id);

        // Références personnelles de l'étudiant — alimentent l'onglet "Ma bibliothèque" du modal APA
        $mesReferences = (! $estEnseignant && ! $user->isAdmin())
            ? $user->etudiantReferences()->get()->map(fn ($r) => [
                'id' => $r->id,
                'titre' => $r->titre,
                'auteurs' => $r->auteurs,
                'annee' => $r->annee,
                'type_source' => $r->type_source,
                'url' => $r->url,
                'doi' => $r->doi,
                'publication' => $r->publication,
            ])->values()
            : collect();

        // Condition commune : membre + non verrouillé + remise encore possible
        $peutAgir = $estMembre && ! $projet->verrouille && $projet->peutEtreRemis();

        // L'enseignant en mode édition peut modifier le contenu comme un membre
        $peutEditer = $peutAgir || ($estEnseignant && (bool) $projet->mode_edition_enseignant);

        // Corrections filtrées selon le rôle :
        // - Enseignant : toutes les corrections
        // - Étudiant : uniquement si correction_visible, et seulement ses corrections + groupe
        $correctionsVisibles = $estEnseignant
            ? $projet->critereCorrections
            : ($projet->correction_visible
                ? $projet->critereCorrections->filter(fn ($c) => $c->user_id === null || $c->user_id === $user->id)
                : collect()
            );

        $correctionsParCritere = $correctionsVisibles
            ->groupBy('critere_id')
            ->map(fn ($corrs) => $corrs->map->only('id', 'user_id', 'points', 'commentaire', 'verifie', 'source_id')->values())
            ->all();

        // Critères globaux — les étudiants ne voient que les critères visibles
        $criteresGlobaux = $typeProjet->criteresGlobaux
            ->when(! $estEnseignant, fn ($col) => $col->where('visible', true))
            ->map->only('id', 'type', 'contenu_type', 'pointage', 'contenu', 'echelle', 'visible', 'ordre')
            ->values();

        // Coches personnelles du membre courant (indicateur local, hors correction)
        $cochesUtilisateur = $estMembre
            ? ProjetCritereEtudiantCoche::where('projet_id', $projet->id)
                ->where('user_id', $user->id)
                ->pluck('critere_id')
                ->values()
                ->all()
            : [];

        return [
            'groupe' => $groupe,
            'classe' => $classe->only('id', 'code', 'cours_id'),
            'cours' => $cours->only('id', 'nom_cours', 'code', 'groupe', 'type_cours'),
            'enseignant' => $cours->enseignant->only('id', 'prenom', 'nom'),
            'membres' => $groupe->membres->map->only('id', 'prenom', 'nom')->values(),
            'projet' => $projet,
            'typeProjet' => $typeProjet->only('id', 'nom'),
            'genererPageTitre' => (bool) $typeProjet->generer_page_titre,
            'genererTableMatieres' => (bool) $typeProjet->generer_table_matieres,
            'aideReference' => (bool) $typeProjet->aide_reference,
            'hasIntroduction' => (bool) $typeProjet->has_introduction,
            'hasConclusionIndividuelle' => (bool) $typeProjet->has_conclusion_individuelle,
            'pageTitreContenu' => $projet->page_titre_contenu,
            'tableMatieresContenu' => $projet->table_matieres_contenu,
            'developpements' => $projet->developpements->map->only('id', 'ordre', 'titre', 'contenu')->values(),
            'conclusions' => $conclusions,
            'peutEditer' => $peutEditer,
            'estEnseignant' => $estEnseignant,
            'correctionVisible' => (bool) $projet->correction_visible,
            'verrouille' => (bool) $projet->verrouille,
            'modeEditionEnseignant' => (bool) $projet->mode_edition_enseignant,
            'dateRemise' => $typeProjet->date_remise?->toIso8601String(),
            'remisLe' => $projet->remis_le?->toIso8601String(),
            'remisesMultiples' => (bool) $typeProjet->remises_multiples,
            'peutRemettre' => $peutAgir,
            'commentaires' => $commentaires,
            'annotationsParChamp' => $annotationsParChamp,
            'votes' => $projet->votes->map(fn (ProjetVoteRemise $v) => [
                'user_id' => $v->user_id,
                'vote' => (bool) $v->vote,
            ])->values(),
            'retardPermis' => (bool) $typeProjet->retard_permis,
            'sections' => $this->sections($projet, $groupe->membres, $groupeTachesParTache, $estEnseignant),
            'renvois' => $projet->renvois->map(function (ProjetRenvoi $r) use ($estEnseignant, $projet) {
                $data = $r->only('id', 'numero', 'contenu', 'type_reference', 'champs_reference');
                $data['commentaires'] = ($estEnseignant || $projet->correction_visible)
                    ? $r->commentaires->map->only('id', 'contenu', 'user_id')->values()
                    : collect();

                return $data;
            })->values(),
            'consentement' => $this->consentement($projet->id, $user->id),
            'mesReferences' => $mesReferences,
            'criteresGlobaux' => $criteresGlobaux,
            'correctionsParCritere' => $correctionsParCritere,
            'cochesUtilisateur' => $cochesUtilisateur,
        ];
    }

    /**
     * Construit le tableau des sections dynamiques avec leur contenu courant.
     *
     * @param  Collection|null  $membres  membres du groupe (requis pour le type 'individuel')
     * @param  Collection|null  $groupeTachesParTache  état des tâches du groupe, indexé par tache_id
     * @return array<int, array<string, mixed>>
     */
    public function sections(ProjetRecherche $projet, ?Collection $membres = null, ?Collection $groupeTachesParTache = null, bool $estEnseignant = true): array
    {
        $sections = $projet->typeProjet?->sections ?? collect();

        if ($sections->isEmpty()) {
            return [];
        }

        // Médias de section (vidéo/audio) — groupés par section_id si déjà chargés
        $mediasParSection = $projet->relationLoaded('sectionMedias')
            ? $projet->sectionMedias->groupBy('section_id')
            : collect();

        $contenusParSection = $projet->sectionContenus->keyBy('section_id');

        $paragraphesParSection = $projet->relationLoaded('sectionParagraphes')
            ? $projet->sectionParagraphes->groupBy('section_id')
            : collect();

        // Conclusions scoped à une section (section_id non null)
        $conclusionsParSectionEtUser = $projet->conclusions
            ->filter(fn (ProjetConclusion $c) => $c->section_id !== null)
            ->groupBy('section_id')
            ->map(fn ($conc) => $conc->keyBy('user_id'));

        // Concepts d'entrevue groupés par section
        $conceptsParSection = $projet->relationLoaded('entrevueConcepts')
            ? $projet->entrevueConcepts->groupBy('section_id')
            : collect();

        // Questions choisies par ce projet, groupées par section_id — pour les sections choix_questions
        $questionsChoisiesParSection = $projet->relationLoaded('questionsChoisies')
            ? $projet->questionsChoisies->groupBy('section_id')
            : collect();

        // Schémas visuels par section_id — pour les sections schema_visuel
        $schemaVisuelsParSection = $projet->relationLoaded('schemaVisuels')
            ? $projet->schemaVisuels->keyBy('section_id')
            : collect();

        return $sections->map(fn (TypeProjetSection $s) => [
            'id' => $s->id,
            'label' => $s->label,
            'description' => $s->description,
            'ordre' => $s->ordre,
            'type' => $s->type ?? 'texte',
            'contenu' => ($s->type === null || $s->type === 'texte')
                ? $contenusParSection->get($s->id)?->contenu
                : null,
            'paragraphes' => $s->type === 'paragraphes'
                ? ($paragraphesParSection->get($s->id)?->map->only('id', 'ordre', 'titre', 'contenu')->values()->all() ?? [])
                : null,
            'conclusionsParMembre' => $s->type === 'individuel' && $membres !== null
                ? $membres->map(fn (User $m) => [
                    'userId' => $m->id,
                    'contenu' => $conclusionsParSectionEtUser->get($s->id)?->get($m->id)?->contenu,
                ])->values()->all()
                : null,
            'concepts' => $s->type === 'entrevue'
                ? ($conceptsParSection->get($s->id)?->map(fn (EntrevueConcept $c) => [
                    'id' => $c->id,
                    'label' => $c->label,
                    'ordre' => $c->ordre,
                    'lignes' => $c->lignes->map->only('id', 'ordre', 'dimension', 'indicateur', 'questions')->values()->all(),
                ])->values()->all() ?? [])
                : null,
            'medias' => in_array($s->type, ['video', 'audio'])
                ? ($mediasParSection->get($s->id)?->map(fn (ProjetSectionMedia $m) => [
                    'id' => $m->id,
                    'source_type' => $m->source_type,
                    'url' => $m->url,
                    'nom_original' => $m->nom_original,
                    'url_publique' => $m->url_publique,
                ])->values()->all() ?? [])
                : null,
            'taches' => $s->type === 'tache'
                ? ($projet->typeProjet?->taches->map(fn (TypeProjetTache $t) => [
                    'id' => $t->id,
                    'titre' => $t->titre,
                    'description' => $t->description,
                    'ordre' => $t->ordre,
                    'assigne_a' => $groupeTachesParTache?->get($t->id)?->assigneA?->only('id', 'prenom', 'nom'),
                    'completed_at' => $groupeTachesParTache?->get($t->id)?->completed_at?->toIso8601String(),
                ])->values()->all() ?? [])
                : null,
            'questions' => $s->type === 'choix_questions'
                ? $s->questionsBanque->map->only('id', 'contenu', 'ordre')->values()->all()
                : null,
            'questionsChoisies' => $s->type === 'choix_questions'
                ? $questionsChoisiesParSection->get($s->id)?->pluck('question_banque_id')->values()->all() ?? []
                : null,
            'schemaVisuel' => $s->type === 'schema_visuel'
                ? ($schemaVisuelsParSection->get($s->id)?->contenu ?? ProjetSchemaVisuel::contenuVide())
                : null,
            'criteres' => $s->relationLoaded('criteres')
                ? $s->criteres
                    ->when(! $estEnseignant, fn ($col) => $col->where('visible', true))
                    ->map->only('id', 'type', 'contenu_type', 'pointage', 'contenu', 'echelle', 'visible', 'ordre')
                    ->values()
                    ->all()
                : [],
            'pointage' => $s->pointage !== null ? (float) $s->pointage : null,
        ])->values()->all();
    }

    /**
     * Retourne le consentement vidéo de l'utilisateur pour ce projet,
     * ou null si aucun consentement n'a encore été enregistré.
     *
     * @return array{accepte: bool, signed_at: string|null}|null
     */
    private function consentement(int $projetId, int $userId): ?array
    {
        $consentement = ConsentementVideo::where('projet_id', $projetId)
            ->where('user_id', $userId)
            ->first();

        if (! $consentement) {
            return null;
        }

        return [
            'accepte' => $consentement->accepte,
            'signed_at' => $consentement->signed_at?->toISOString(),
        ];
    }
}
