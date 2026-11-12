<?php

namespace App\Services;

use App\Helpers\HtmlHelper;
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

/**
 * Construit les props Inertia de l'éditeur et de l'aperçu du projet de recherche.
 */
class ProjetRechercheProps
{
    /**
     * Retourne les props de la page Projets/Edit pour l'utilisateur donné.
     *
     * Charge les relations du projet en une passe (évite le N+1) et filtre
     * annotations, corrections et critères selon le rôle.
     *
     * @return array<string, mixed>
     */
    public function pourEditeur(
        Cours $cours, Classe $classe, Groupe $groupe,
        TypeProjet $typeProjet, ProjetRecherche $projet,
        User $user, bool $estEnseignant): array
    {
        // Précharger en une seule requête chacune des relations — évite le N+1
        $projet->load(['conclusions', 'commentaires', 'annotations', 'developpements', 'votes', 'typeProjet.sections.questionsBanque', 'typeProjet.sections.criteres', 'typeProjet.criteresGlobaux', 'typeProjet.taches', 'sectionContenus', 'sectionParagraphes', 'entrevueConcepts.lignes', 'sectionMedias', 'questionsChoisies', 'schemaVisuels', 'renvois.commentaires', 'critereCorrections']);

        $estMembre = ! $estEnseignant && $groupe->membres->contains('id', $user->id);

        // Condition commune : membre + non verrouillé + remise encore possible
        $peutAgir = $estMembre && ! $projet->verrouille && $projet->peutEtreRemis();

        return [
            ...$this->contexte($cours, $classe, $groupe, $typeProjet),
            ...$this->optionsTypeProjet($typeProjet),
            ...$this->etatProjet($projet),
            'projet' => $projet,
            'developpements' => $projet->developpements->map->only('id', 'ordre', 'titre', 'contenu')->values(),
            'conclusions' => $this->conclusions($projet, $groupe),
            // L'enseignant en mode édition peut modifier le contenu comme un membre
            'peutEditer' => $peutAgir || ($estEnseignant && (bool) $projet->mode_edition_enseignant),
            'estEnseignant' => $estEnseignant,
            'peutRemettre' => $peutAgir,
            'commentaires' => $this->commentaires($projet),
            'annotationsParChamp' => $this->annotationsParChamp($projet, $estEnseignant),
            'votes' => $projet->votes->map(fn (ProjetVoteRemise $v) => [
                'user_id' => $v->user_id,
                'vote' => (bool) $v->vote,
            ])->values(),
            'sections' => $this->sections($projet, $groupe->membres, $this->tachesDuGroupe($typeProjet, $groupe), $estEnseignant),
            'renvois' => $this->renvois($projet, $estEnseignant),
            'consentement' => $this->consentement($projet->id, $user->id),
            'mesReferences' => $this->mesReferences($user, $estEnseignant),
            'criteresGlobaux' => $this->criteresVisibles($typeProjet->criteresGlobaux, $estEnseignant),
            'correctionsParCritere' => $this->correctionsParCritere($projet, $user, $estEnseignant),
            'cochesUtilisateur' => $estMembre ? $this->cochesUtilisateur($projet, $user) : [],
        ];
    }

    /**
     * Retourne les props de la page Projets/Apercu (lecture seule, sans annotations ni contrôles).
     *
     * Le projet n'est jamais créé ici : s'il n'existe pas encore, l'aperçu est vide.
     *
     * @return array<string, mixed>
     */
    public function pourApercu(
        Cours $cours, Classe $classe, Groupe $groupe,
        TypeProjet $typeProjet, bool $estEnseignant): array
    {
        $projet = ProjetRecherche::where('groupe_id', $groupe->id)
            ->where('type_projet_id', $typeProjet->id)
            ->with(['typeProjet.sections', 'sectionContenus', 'sectionParagraphes', 'conclusions', 'entrevueConcepts.lignes', 'renvois'])
            ->first();

        return [
            'groupe' => $groupe->only('id', 'numero', 'classe_id'),
            'classe' => $classe->only('id', 'code', 'cours_id'),
            'cours' => $cours->only('id', 'nom_cours', 'code', 'groupe'),
            'typeProjet' => $typeProjet->only('id', 'nom'),
            'thematiques' => $groupe->thematiques->map->only('id', 'nom'),
            'membres' => $groupe->membres->map->only('id', 'prenom', 'nom')->values(),
            'projet' => $projet ? ['id' => $projet->id, 'titre_projet' => $projet->titre_projet] : null,
            'sections' => $projet
                ? collect($this->sections($projet, $groupe->membres))->map(fn (array $s) => $this->sectionApercu($s))->values()
                : collect(),
            'renvois' => $projet ? $projet->renvois->map->only('id', 'numero', 'contenu')->values() : collect(),
            'estEnseignant' => $estEnseignant,
        ];
    }

    /**
     * Réduit une section de l'éditeur à ce que l'aperçu affiche, sans les marques d'annotation.
     *
     * @param  array<string, mixed>  $s
     * @return array<string, mixed>
     */
    private function sectionApercu(array $s): array
    {
        return [
            'id' => $s['id'],
            'label' => $s['label'],
            'description' => $s['description'],
            'ordre' => $s['ordre'],
            'type' => $s['type'],
            'contenu' => $s['type'] === 'texte' ? HtmlHelper::stripAnnotationMarks($s['contenu']) : null,
            'paragraphes' => $s['type'] === 'paragraphes' ? $this->paragraphesApercu($s['paragraphes'] ?? []) : null,
            'conclusionsParMembre' => $s['type'] === 'individuel'
                ? $this->conclusionsApercu($s['conclusionsParMembre'] ?? [])
                : null,
            // Les concepts d'entrevue n'ont pas d'annotations HTML à nettoyer
            'concepts' => $s['type'] === 'entrevue' ? ($s['concepts'] ?? []) : null,
        ];
    }

    /**
     * Retourne les paragraphes d'une section sans les marques d'annotation.
     *
     * @param  array<int, array<string, mixed>>  $paragraphes
     * @return array<int, array<string, mixed>>
     */
    private function paragraphesApercu(array $paragraphes): array
    {
        return collect($paragraphes)->map(fn (array $p) => [
            'id' => $p['id'],
            'ordre' => $p['ordre'],
            'titre' => $p['titre'],
            'contenu' => HtmlHelper::stripAnnotationMarks($p['contenu']),
        ])->values()->all();
    }

    /**
     * Retourne les conclusions individuelles rédigées (les vides sont omises), sans marques d'annotation.
     *
     * @param  array<int, array<string, mixed>>  $conclusions
     * @return array<int, array<string, mixed>>
     */
    private function conclusionsApercu(array $conclusions): array
    {
        return collect($conclusions)
            ->filter(fn (array $c) => trim(strip_tags((string) ($c['contenu'] ?? ''))) !== '')
            ->map(fn (array $c) => [
                'userId' => $c['userId'],
                'contenu' => HtmlHelper::stripAnnotationMarks($c['contenu']),
            ])->values()->all();
    }

    /**
     * Construit le tableau des sections dynamiques avec leur contenu courant.
     *
     * Chaque section expose toutes les clés de contenu ; seules celles de son type sont remplies.
     *
     * @param  Collection|null  $membres  membres du groupe (requis pour le type 'individuel')
     * @param  Collection|null  $groupeTachesParTache  état des tâches du groupe, indexé par tache_id
     * @return array<int, array<string, mixed>>
     */
    public function sections(
        ProjetRecherche $projet, ?Collection $membres = null,
        ?Collection $groupeTachesParTache = null, bool $estEnseignant = true): array
    {
        $sections = $projet->typeProjet?->sections ?? collect();

        if ($sections->isEmpty()) {
            return [];
        }

        $index = $this->indexerContenus($projet);

        return $sections->map(fn (TypeProjetSection $s) => [
            'id' => $s->id,
            'label' => $s->label,
            'description' => $s->description,
            'ordre' => $s->ordre,
            'type' => $s->type ?? 'texte',
            'contenu' => null,
            'paragraphes' => null,
            'conclusionsParMembre' => null,
            'concepts' => null,
            'medias' => null,
            'taches' => null,
            'questions' => null,
            'questionsChoisies' => null,
            'schemaVisuel' => null,
            ...$this->contenuParType($s, $projet, $index, $membres, $groupeTachesParTache),
            'criteres' => $s->relationLoaded('criteres')
                ? $this->criteresVisibles($s->criteres, $estEnseignant)->all()
                : [],
            'pointage' => $s->pointage !== null ? (float) $s->pointage : null,
        ])->values()->all();
    }

    /**
     * Retourne le contexte de navigation de la page (cours, classe, groupe, membres).
     *
     * @return array<string, mixed>
     */
    private function contexte(
        Cours $cours, Classe $classe,
        Groupe $groupe, TypeProjet $typeProjet): array
    {
        return [
            'groupe' => $groupe,
            'classe' => $classe->only('id', 'code', 'cours_id'),
            'cours' => $cours->only('id', 'nom_cours', 'code', 'groupe', 'type_cours'),
            'enseignant' => $cours->enseignant->only('id', 'prenom', 'nom'),
            'membres' => $groupe->membres->map->only('id', 'prenom', 'nom')->values(),
            'typeProjet' => $typeProjet->only('id', 'nom'),
        ];
    }

    /**
     * Retourne les réglages du type de projet qui pilotent l'éditeur et la remise.
     *
     * @return array<string, mixed>
     */
    private function optionsTypeProjet(TypeProjet $typeProjet): array
    {
        return [
            'genererPageTitre' => (bool) $typeProjet->generer_page_titre,
            'genererTableMatieres' => (bool) $typeProjet->generer_table_matieres,
            'aideReference' => (bool) $typeProjet->aide_reference,
            'hasIntroduction' => (bool) $typeProjet->has_introduction,
            'hasConclusionIndividuelle' => (bool) $typeProjet->has_conclusion_individuelle,
            'dateRemise' => $typeProjet->date_remise?->toIso8601String(),
            'remisesMultiples' => (bool) $typeProjet->remises_multiples,
            'retardPermis' => (bool) $typeProjet->retard_permis,
        ];
    }

    /**
     * Retourne l'état courant du projet (verrou, correction, remise, pages générées).
     *
     * @return array<string, mixed>
     */
    private function etatProjet(ProjetRecherche $projet): array
    {
        return [
            'pageTitreContenu' => $projet->page_titre_contenu,
            'tableMatieresContenu' => $projet->table_matieres_contenu,
            'correctionVisible' => (bool) $projet->correction_visible,
            'verrouille' => (bool) $projet->verrouille,
            'modeEditionEnseignant' => (bool) $projet->mode_edition_enseignant,
            'remisLe' => $projet->remis_le?->toIso8601String(),
        ];
    }

    /**
     * Retourne l'état des tâches du groupe, indexé par tache_id pour un accès O(1) dans sections().
     */
    private function tachesDuGroupe(TypeProjet $typeProjet, Groupe $groupe): Collection
    {
        $tacheIds = $typeProjet->taches()->pluck('id');

        if ($tacheIds->isEmpty()) {
            return collect();
        }

        return GroupeTache::where('groupe_id', $groupe->id)
            ->whereIn('tache_id', $tacheIds)
            ->with('assigneA:id,prenom,nom')
            ->get()
            ->keyBy('tache_id');
    }

    /**
     * Retourne la conclusion générale (hors section) de chaque membre du groupe.
     */
    private function conclusions(ProjetRecherche $projet, Groupe $groupe): Collection
    {
        $conclusionsParMembre = $projet->conclusions->keyBy('user_id');

        return $groupe->membres->map(fn (User $membre): array => [
            'etudiant' => $membre->only('id', 'prenom', 'nom'),
            'contenu' => $conclusionsParMembre->get($membre->id)?->contenu,
        ]);
    }

    /**
     * Retourne les commentaires indexés par champ pour un accès O(1) côté Vue.
     */
    private function commentaires(ProjetRecherche $projet): Collection
    {
        return $projet->commentaires->keyBy('champ')->map(fn (ProjetCommentaire $c) => [
            'id' => $c->id,
            'contenu' => $c->contenu,
        ]);
    }

    /**
     * Retourne les annotations inline indexées par champ, triées par leur position persistée.
     *
     * Les étudiants ne voient aucune annotation tant que la correction n'est pas visible.
     */
    private function annotationsParChamp(ProjetRecherche $projet, bool $estEnseignant): Collection
    {
        $annotations = $estEnseignant
            ? $projet->annotations
            : $projet->annotations->when(! $projet->correction_visible, fn ($coll) => $coll->whereNull('id'));

        return $annotations
            ->groupBy('champ')
            ->map(fn (Collection $parChamp) => $parChamp
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
                ->values());
    }

    /**
     * Retourne les renvois du projet ; leurs commentaires restent masqués aux étudiants
     * tant que la correction n'est pas visible.
     */
    private function renvois(ProjetRecherche $projet, bool $estEnseignant): Collection
    {
        return $projet->renvois->map(function (ProjetRenvoi $r) use ($estEnseignant, $projet) {
            $data = $r->only('id', 'numero', 'contenu', 'type_reference', 'champs_reference');
            $data['commentaires'] = ($estEnseignant || $projet->correction_visible)
                ? $r->commentaires->map->only('id', 'contenu', 'user_id')->values()
                : collect();

            return $data;
        })->values();
    }

    /**
     * Retourne les références personnelles de l'étudiant, qui alimentent l'onglet
     */
    private function mesReferences(User $user, bool $estEnseignant): Collection
    {
        if ($estEnseignant || $user->isAdmin()) {
            return collect();
        }

        return $user->etudiantReferences()->get()->map(fn ($r) => [
            'id' => $r->id,
            'titre' => $r->titre,
            'auteurs' => $r->auteurs,
            'annee' => $r->annee,
            'type_source' => $r->type_source,
            'url' => $r->url,
            'doi' => $r->doi,
            'publication' => $r->publication,
        ])->values();
    }

    /**
     * Retourne les critères affichables : les étudiants ne voient que les critères visibles.
     */
    private function criteresVisibles(Collection $criteres, bool $estEnseignant): Collection
    {
        return $criteres
            ->when(! $estEnseignant, fn ($col) => $col->where('visible', true))
            ->map->only('id', 'type', 'contenu_type', 'pointage', 'contenu', 'echelle', 'visible', 'ordre')
            ->values();
    }

    /**
     * Retourne les corrections groupées par critère selon le rôle.
     *
     * L'enseignant voit tout ; l'étudiant ne voit, une fois la correction visible,
     * que les corrections de groupe et les siennes.
     *
     * @return array<int, Collection>
     */
    private function correctionsParCritere(ProjetRecherche $projet, User $user, bool $estEnseignant): array
    {
        $corrections = match (true) {
            $estEnseignant => $projet->critereCorrections,
            (bool) $projet->correction_visible => $projet->critereCorrections
                ->filter(fn ($c) => $c->user_id === null || $c->user_id === $user->id),
            default => collect(),
        };

        return $corrections
            ->groupBy('critere_id')
            ->map(fn ($corrs) => $corrs->map->only('id', 'user_id', 'points', 'commentaire', 'verifie', 'source_id')->values())
            ->all();
    }

    /**
     * Retourne les critères cochés par le membre courant (indicateur local, hors correction).
     *
     * @return list<int>
     */
    private function cochesUtilisateur(ProjetRecherche $projet, User $user): array
    {
        return ProjetCritereEtudiantCoche::where('projet_id', $projet->id)
            ->where('user_id', $user->id)
            ->pluck('critere_id')
            ->values()
            ->all();
    }

    /**
     * Indexe par section les contenus déjà chargés du projet.
     *
     * Une relation non chargée donne un index vide plutôt qu'une requête par section.
     *
     * @return array{contenus: Collection, paragraphes: Collection, conclusions: Collection, concepts: Collection, medias: Collection, questionsChoisies: Collection, schemas: Collection}
     */
    private function indexerContenus(ProjetRecherche $projet): array
    {
        $grouper = fn (string $relation) => $projet->relationLoaded($relation)
            ? $projet->{$relation}->groupBy('section_id')
            : collect();

        return [
            'contenus' => $projet->sectionContenus->keyBy('section_id'),
            'paragraphes' => $grouper('sectionParagraphes'),
            'conclusions' => $projet->conclusions
                ->filter(fn (ProjetConclusion $c) => $c->section_id !== null)
                ->groupBy('section_id')
                ->map(fn ($conc) => $conc->keyBy('user_id')),
            'concepts' => $grouper('entrevueConcepts'),
            'medias' => $grouper('sectionMedias'),
            'questionsChoisies' => $grouper('questionsChoisies'),
            'schemas' => $projet->relationLoaded('schemaVisuels')
                ? $projet->schemaVisuels->keyBy('section_id')
                : collect(),
        ];
    }

    /**
     * Retourne les clés de contenu propres au type de la section.
     *
     * @param  array<string, Collection>  $index  résultat de indexerContenus()
     * @return array<string, mixed>
     */
    private function contenuParType(
        TypeProjetSection $s, ProjetRecherche $projet, array $index,
        ?Collection $membres, ?Collection $groupeTachesParTache): array
    {
        return match ($s->type ?? 'texte') {
            'texte' => ['contenu' => $index['contenus']->get($s->id)?->contenu],
            'paragraphes' => [
                'paragraphes' => $index['paragraphes']->get($s->id)?->map->only('id', 'ordre', 'titre',
                    'contenu')->values()->all() ?? [],
            ],
            'individuel' => $membres === null ? [] : [
                'conclusionsParMembre' => $membres->map(fn (User $m) => [
                    'userId' => $m->id,
                    'contenu' => $index['conclusions']->get($s->id)?->get($m->id)?->contenu,
                ])->values()->all(),
            ],
            'entrevue' => ['concepts' => $this->concepts($index['concepts']->get($s->id))],
            'video', 'audio' => ['medias' => $this->medias($index['medias']->get($s->id))],
            'tache' => ['taches' => $this->taches($projet, $groupeTachesParTache)],
            'choix_questions' => [
                'questions' => $s->questionsBanque->map->only('id', 'contenu', 'ordre')
                    ->values()->all(),
                'questionsChoisies' => $index['questionsChoisies']->get($s->id)?->pluck(
                    'question_banque_id')->values()->all() ?? [],
            ],
            'schema_visuel' => [
                'schemaVisuel' => $index['schemas']->get($s->id)?->contenu ??
                    ProjetSchemaVisuel::contenuVide(),
            ],
            default => [],
        };
    }

    /**
     * Retourne les concepts d'une section d'entrevue avec leurs lignes.
     *
     * @return list<array<string, mixed>>
     */
    private function concepts(?Collection $concepts): array
    {
        return $concepts?->map(fn (EntrevueConcept $c) => [
            'id' => $c->id,
            'label' => $c->label,
            'ordre' => $c->ordre,
            'lignes' => $c->lignes->map->only('id', 'ordre', 'dimension', 'indicateur',
                'questions')->values()->all(),
        ])->values()->all() ?? [];
    }

    /**
     * Retourne les médias (vidéo/audio) d'une section.
     *
     * @return list<array<string, mixed>>
     */
    private function medias(?Collection $medias): array
    {
        return $medias?->map(fn (ProjetSectionMedia $m) => [
            'id' => $m->id,
            'source_type' => $m->source_type,
            'url' => $m->url,
            'nom_original' => $m->nom_original,
            'url_publique' => $m->url_publique,
        ])->values()->all() ?? [];
    }

    /**
     * Retourne les tâches du type de projet avec leur état (assignation, complétion) pour le groupe.
     *
     * @return list<array<string, mixed>>
     */
    private function taches(ProjetRecherche $projet, ?Collection $groupeTachesParTache): array
    {
        return $projet->typeProjet?->taches->map(fn (TypeProjetTache $t) => [
            'id' => $t->id,
            'titre' => $t->titre,
            'description' => $t->description,
            'ordre' => $t->ordre,
            'assigne_a' => $groupeTachesParTache?->get($t->id)?->assigneA?->only('id', 'prenom', 'nom'),
            'completed_at' => $groupeTachesParTache?->get($t->id)?->completed_at?->toIso8601String(),
        ])->values()->all() ?? [];
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
