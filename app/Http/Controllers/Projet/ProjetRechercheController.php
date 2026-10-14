<?php

namespace App\Http\Controllers\Projet;

use App\Enums\StatutProjetRecherche;
use App\Helpers\HtmlHelper;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Projet\Concerns\AutoriseProjetRecherche;
use App\Http\Controllers\Projet\Concerns\RendMuseeProjet;
use App\Models\Classe;
use App\Models\ConsentementVideo;
use App\Models\Cours;
use App\Models\EntrevueConcept;
use App\Models\Groupe;
use App\Models\GroupeTache;
use App\Models\MuseePublication;
use App\Models\ProjetAnnotation;
use App\Models\ProjetCommentaire;
use App\Models\ProjetConclusion;
use App\Models\ProjetCritereEtudiantCoche;
use App\Models\ProjetRecherche;
use App\Models\ProjetRenvoi;
use App\Models\ProjetSchemaVisuel;
use App\Models\ProjetSectionContenu;
use App\Models\ProjetSectionMedia;
use App\Models\ProjetSectionParagraphe;
use App\Models\ProjetVoteRemise;
use App\Models\TypeProjet;
use App\Models\TypeProjetSection;
use App\Models\TypeProjetTache;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Projet de recherche d'un groupe : liste, éditeur, aperçu, contenu et réglages de l'enseignant.
 */
class ProjetRechercheController extends Controller
{
    use AutoriseProjetRecherche;
    use RendMuseeProjet;

    /**
     * Affiche toutes les cartes de projets disponibles pour ce groupe.
     *
     * Retourne un tableau de TypeProjets accessibles de l'enseignant du cours,
     * chacun accompagné du ProjetRecherche correspondant (ou null si non encore créé).
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function index(Cours $cours, Classe $classe, Groupe $groupe): Response
    {
        abort_if($classe->cours_id !== $cours->id, 404);
        abort_if($groupe->classe_id !== $classe->id, 404);

        $groupe->load(['membres', 'classe.cours']);
        $this->authorize('view', $groupe);

        $user = auth()->user();
        $estEnseignant = $user->isEnseignant()
            && $cours->enseignant_id === $user->id;

        // Charger les TypeProjets du cours — pas de tous les cours de l'enseignant
        $query = TypeProjet::where('cours_id', $cours->id);

        // Les étudiants ne voient que les types rendus accessibles par l'enseignant
        if (! $estEnseignant && $user->role !== 'admin') {
            $query->where('accessible', true);
        }

        $typesProjets = $query->get();

        // Précharger tous les projets de ce groupe en une seule requête — évite le N+1
        $projetsParType = ProjetRecherche::where('groupe_id', $groupe->id)
            ->whereIn('type_projet_id', $typesProjets->pluck('id'))
            ->with(['typeProjet', 'conclusions', 'museePublication'])
            ->get()
            ->keyBy('type_projet_id');

        $projets = $typesProjets->map(function (TypeProjet $typeProjet) use ($groupe, $projetsParType): array {
            $projet = $projetsParType->get($typeProjet->id);

            $conclusionsParMembre = $projet ? $projet->conclusions->keyBy('user_id') : collect();

            $conclusions = $groupe->membres->map(function (User $membre) use ($conclusionsParMembre): array {
                $conclusion = $conclusionsParMembre->get($membre->id);

                return [
                    'etudiant' => $membre->only('id', 'prenom', 'nom'),
                    'a_redige' => $conclusion !== null && trim(strip_tags((string) ($conclusion->contenu ?? ''))) !== '',
                ];
            });

            return [
                'typeProjet' => array_merge(
                    $typeProjet->only('id', 'nom', 'description', 'accessible'),
                    ['type' => $typeProjet->type],
                ),
                'projet' => $projet
                    ? [
                        'id' => $projet->id,
                        'titre_projet' => $projet->titre_projet,
                        'completion' => $projet->completion(),
                        'statut' => $projet->synchroniserStatut()->value,
                        'statut_publication' => $typeProjet->isMusee()
                            ? ($projet->museePublication?->statut ?? MuseePublication::STATUT_BROUILLON)
                            : null,
                    ]
                    : null,
                'statut' => $projet?->statutActuel()->value
                    ?? StatutProjetRecherche::fromDates($typeProjet->date_remise, null)->value,
                'conclusions' => $conclusions,
            ];
        });

        return Inertia::render('Projets/Index', [
            'groupe' => $groupe->only('id', 'code', 'classe_id'),
            'classe' => $classe->only('id', 'code', 'cours_id'),
            'cours' => $cours->only('id', 'nom_cours', 'code', 'groupe'),
            'projets' => $projets,
            'estEnseignant' => $estEnseignant,
        ]);
    }

    /**
     * Affiche le projet partagé avec l'éditeur de contenu et les conclusions individuelles.
     *
     * Crée le projet s'il n'existe pas encore (premier accès à l'éditeur).
     * Utilise un eager load des conclusions, commentaires et notes pour éviter le N+1.
     * Filtre les annotations de type "correction" pour les étudiants si correction_visible = false.
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function show(Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet): Response
    {
        abort_if($classe->cours_id !== $cours->id, 404);
        abort_if($groupe->classe_id !== $classe->id, 404);
        $this->verifierTypeProjetAppartientCours($typeProjet, $cours);

        $groupe->load(['membres', 'thematiques', 'classe.cours.enseignant']);
        $cours->loadMissing('enseignant');
        $this->authorize('view', $groupe);

        $user = auth()->user();
        $estEnseignant = $user->isEnseignant()
            && $cours->enseignant_id === $user->id;

        // Guard accessibilité : si le type de projet n'est pas accessible, les étudiants ne peuvent pas accéder
        if (! $estEnseignant && $user->role !== 'admin') {
            abort_if(! $typeProjet->accessible, 403, 'Ce type de projet n\'est pas encore accessible.');
        }

        // Créer le projet partagé s'il n'existe pas encore (accès à l'éditeur implique volonté de créer)
        $projet = ProjetRecherche::firstOrCreate([
            'groupe_id' => $groupe->id,
            'type_projet_id' => $typeProjet->id,
        ]);

        // Les projets musée ont leur propre éditeur — on les rend séparément
        if ($typeProjet->isMusee()) {
            return $this->renderMuseeShow($cours, $classe, $groupe, $typeProjet, $projet, $estEnseignant);
        }

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
        $mesReferences = (! $estEnseignant && $user->role !== 'admin')
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

        return Inertia::render('Projets/Show', [
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
            'sections' => $this->construireSections($projet, $groupe->membres, $groupeTachesParTache, $estEnseignant),
            'renvois' => $projet->renvois->map(function (ProjetRenvoi $r) use ($estEnseignant, $projet) {
                $data = $r->only('id', 'numero', 'contenu', 'type_reference', 'champs_reference');
                $data['commentaires'] = ($estEnseignant || $projet->correction_visible)
                    ? $r->commentaires->map->only('id', 'contenu', 'user_id')->values()
                    : collect();

                return $data;
            })->values(),
            'consentement' => $this->construireConsentement($projet->id, $user->id),
            'mesReferences' => $mesReferences,
            'criteresGlobaux' => $criteresGlobaux,
            'correctionsParCritere' => $correctionsParCritere,
            'cochesUtilisateur' => $cochesUtilisateur,
        ]);
    }

    /**
     * Affiche le projet en mode aperçu (lecture seule, sans annotations ni contrôles).
     *
     * Accessible aux membres du groupe et à l'enseignant du cours.
     * Rend les sections dynamiques des 3 types (texte, paragraphes, individuel).
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function apercu(Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet): Response
    {
        abort_if($classe->cours_id !== $cours->id, 404);
        abort_if($groupe->classe_id !== $classe->id, 404);
        $this->verifierTypeProjetAppartientCours($typeProjet, $cours);

        $groupe->load(['membres', 'thematiques', 'classe.cours']);
        $this->authorize('view', $groupe);

        $user = auth()->user();
        $estEnseignant = $user->isEnseignant()
            && $cours->enseignant_id === $user->id;

        $projet = ProjetRecherche::where('groupe_id', $groupe->id)
            ->where('type_projet_id', $typeProjet->id)
            ->with(['typeProjet.sections', 'sectionContenus', 'sectionParagraphes', 'conclusions', 'entrevueConcepts.lignes', 'renvois'])
            ->first();

        $sections = $projet
            ? collect($this->construireSections($projet, $groupe->membres))->map(fn (array $s) => [
                'id' => $s['id'],
                'label' => $s['label'],
                'description' => $s['description'],
                'ordre' => $s['ordre'],
                'type' => $s['type'],
                'contenu' => $s['type'] === 'texte'
                    ? HtmlHelper::stripAnnotationMarks($s['contenu'])
                    : null,
                'paragraphes' => $s['type'] === 'paragraphes'
                    ? collect($s['paragraphes'] ?? [])->map(fn (array $p) => [
                        'id' => $p['id'],
                        'ordre' => $p['ordre'],
                        'titre' => $p['titre'],
                        'contenu' => HtmlHelper::stripAnnotationMarks($p['contenu']),
                    ])->values()->all()
                    : null,
                'conclusionsParMembre' => $s['type'] === 'individuel'
                    ? collect($s['conclusionsParMembre'] ?? [])
                        ->filter(fn (array $c) => trim(strip_tags((string) ($c['contenu'] ?? ''))) !== '')
                        ->map(fn (array $c) => [
                            'userId' => $c['userId'],
                            'contenu' => HtmlHelper::stripAnnotationMarks($c['contenu']),
                        ])->values()->all()
                    : null,
                // Les concepts d'entrevue sont passés tels quels dans l'aperçu (pas d'annotations HTML à nettoyer)
                'concepts' => $s['type'] === 'entrevue' ? ($s['concepts'] ?? []) : null,
            ])->values()
            : collect();

        return Inertia::render('Projets/Apercu', [
            'groupe' => $groupe->only('id', 'numero', 'classe_id'),
            'classe' => $classe->only('id', 'code', 'cours_id'),
            'cours' => $cours->only('id', 'nom_cours', 'code', 'groupe'),
            'typeProjet' => $typeProjet->only('id', 'nom'),
            'thematiques' => $groupe->thematiques->map->only('id', 'nom'),
            'membres' => $groupe->membres->map->only('id', 'prenom', 'nom')->values(),
            'projet' => $projet
                ? ['id' => $projet->id, 'titre_projet' => $projet->titre_projet]
                : null,
            'sections' => $sections,
            'renvois' => $projet
                ? $projet->renvois->map->only('id', 'numero', 'contenu')->values()
                : collect(),
            'estEnseignant' => $estEnseignant,
        ]);
    }

    /**
     * Met à jour le titre du projet et, le contenu manuel de la page titre
     * et de la table des matières (utilisés quand les flags de génération sont désactivés).
     *
     * @throws HttpException
     */
    public function update(Request $request, Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet): JsonResponse
    {
        $this->verifierTypeProjetAppartientCours($typeProjet, $cours);

        $validated = $request->validate([
            'titre_projet' => ['sometimes', 'nullable', 'string', 'max:500'],
            'page_titre_contenu' => ['sometimes', 'nullable', 'string', 'max:1500'],
            'table_matieres_contenu' => ['sometimes', 'nullable', 'string', 'max:1500'],
        ]);

        $existant = ProjetRecherche::where('groupe_id', $groupe->id)
            ->where('type_projet_id', $typeProjet->id)
            ->first();

        if ($existant !== null) {
            $this->verifierEditionContenuAutorisee($cours, $classe, $groupe, $existant);
            $existant->update($validated);
            $projet = $existant;
        } else {
            // Le projet n'existe pas encore — seul un membre du groupe peut le créer
            abort_if($classe->cours_id !== $cours->id, 404);
            abort_if($groupe->classe_id !== $classe->id, 404);
            $groupe->loadMissing('classe.cours');
            $this->authorize('manageThematiques', $groupe);
            $projet = ProjetRecherche::create([
                'groupe_id' => $groupe->id,
                'type_projet_id' => $typeProjet->id,
                ...$validated,
            ]);
        }

        return response()->json([
            'message' => 'saved',
            'completion' => $projet->completion(),
        ]);
    }

    /**
     * Sauvegarde le contenu HTML d'une section dynamique pour un projet.
     *
     * Vérifie que la section appartient bien au TypeProjet du groupe (anti-IDOR).
     *
     * @throws HttpException
     */
    public function updateSectionContenu(Request $request, Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, TypeProjetSection $section): JsonResponse
    {
        $this->verifierTypeProjetAppartientCours($typeProjet, $cours);
        // Vérifier que la section appartient au TypeProjet passé en URL — évite l'IDOR
        abort_if($section->type_projet_id !== $typeProjet->id, 404);

        $projet = $this->trouverProjet($groupe, $typeProjet);
        $this->verifierEditionContenuAutorisee($cours, $classe, $groupe, $projet);

        $validated = $request->validate([
            'contenu' => ['nullable', 'string'],
        ]);

        ProjetSectionContenu::updateOrCreate(
            ['projet_id' => $projet->id, 'section_id' => $section->id],
            ['contenu' => $validated['contenu']],
        );

        if ($validated['contenu'] !== null) {
            $this->supprimerAnnotationsOrphelines($projet, 'section_'.$section->id, $validated['contenu']);
        }

        return response()->json([
            'message' => 'saved',
            'completion' => $projet->fresh()->load(['typeProjet.sections', 'sectionContenus'])->completion(),
        ]);
    }

    /**
     * Sauvegarde la conclusion individuelle d'un membre du groupe.
     *
     * N'importe quel membre du groupe peut modifier la conclusion d'un autre membre.
     * Le user_id cible doit être validé comme membre du groupe pour éviter l'IDOR.
     *
     * @throws HttpException
     */
    public function updateConclusion(Request $request, Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet): JsonResponse
    {
        $this->verifierTypeProjetAppartientCours($typeProjet, $cours);

        // Charger les membres pour valider le user_id cible (toujours nécessaire, même pour l'enseignant)
        $groupe->load(['classe.cours', 'membres']);

        // Vérifier l'autorisation avant la validation pour retourner 403 plutôt que 422
        // aux utilisateurs qui ne font pas partie du groupe et ne sont pas l'enseignant.
        abort_unless(
            $groupe->membres->contains('id', auth()->id()) || $cours->enseignant_id === auth()->id(),
            403,
        );

        $validated = $request->validate([
            'contenu' => ['nullable', 'string'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'section_id' => ['nullable', 'integer', Rule::exists('type_projet_sections', 'id')->where('type_projet_id', $typeProjet->id)],
        ]);

        abort_unless(
            $groupe->membres->contains('id', $validated['user_id']),
            422,
            'Cet étudiant n\'est pas membre du groupe.',
        );

        $projet = ProjetRecherche::firstOrCreate([
            'groupe_id' => $groupe->id,
            'type_projet_id' => $typeProjet->id,
        ]);

        $this->verifierEditionContenuAutorisee($cours, $classe, $groupe, $projet);

        $clé = ['projet_id' => $projet->id, 'user_id' => $validated['user_id']];
        if (isset($validated['section_id'])) {
            $clé['section_id'] = $validated['section_id'];
        }

        ProjetConclusion::updateOrCreate(
            $clé,
            ['contenu' => $validated['contenu']],
        );

        return response()->json(['message' => 'saved']);
    }

    /**
     * Verrouille ou déverrouille le document pour l'édition par les étudiants.
     *
     * @throws HttpException
     */
    public function toggleVerrouille(Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet): JsonResponse
    {
        $this->verifierTypeProjetAppartientCours($typeProjet, $cours);
        $this->autoriserEnseignant($cours, $classe, $groupe);

        $projet = ProjetRecherche::firstOrCreate([
            'groupe_id' => $groupe->id,
            'type_projet_id' => $typeProjet->id,
        ]);

        $projet->update(['verrouille' => ! $projet->verrouille]);

        return response()->json([
            'message' => 'toggled',
            'verrouille' => (bool) $projet->verrouille,
        ]);
    }

    /**
     * Active ou désactive le mode édition enseignant pour le projet d'une équipe.
     *
     * Quand actif, l'enseignant peut modifier directement le contenu du projet
     * (titre, sections, développements, conclusions, renvois) sans être membre.
     *
     * @throws HttpException
     */
    public function toggleModeEditionEnseignant(Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet): JsonResponse
    {
        $this->verifierTypeProjetAppartientCours($typeProjet, $cours);
        $this->autoriserEnseignant($cours, $classe, $groupe);

        $projet = ProjetRecherche::firstOrCreate([
            'groupe_id' => $groupe->id,
            'type_projet_id' => $typeProjet->id,
        ]);

        $projet->update(['mode_edition_enseignant' => ! $projet->mode_edition_enseignant]);

        return response()->json([
            'message' => 'toggled',
            'mode_edition_enseignant' => (bool) $projet->mode_edition_enseignant,
        ]);
    }

    // ─── Musée virtuel ────────────────────────────────────────────────────────

    /**
     * Retourne le consentement vidéo de l'utilisateur pour ce projet,
     * ou null si aucun consentement n'a encore été enregistré.
     *
     * @return array{accepte: bool, signed_at: string|null}|null
     */
    private function construireConsentement(int $projetId, int $userId): ?array
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

    /**
     * Construit le tableau des sections dynamiques avec leur contenu courant.
     *
     * Selon le type de section :
     * - 'texte'         → champ `contenu` (ProjetSectionContenu)
     * - 'paragraphes'   → champ `paragraphes` (liste ProjetSectionParagraphe triée par ordre)
     * - 'individuel'    → champ `conclusionsParMembre` (1 entrée par membre du groupe)
     * - 'entrevue'      → champ `concepts` (liste EntrevueConcept avec leurs lignes)
     * - 'tache'         → champ `taches` (liste TypeProjetTache + état GroupeTache du groupe)
     *
     * @param  Collection|null  $membres  membres du groupe (requis pour le type 'individuel')
     * @param  Collection|null  $groupeTachesParTache  état des tâches du groupe, indexé par tache_id
     * @return array<int, array<string, mixed>>
     */
    private function construireSections(ProjetRecherche $projet, ?Collection $membres = null, ?Collection $groupeTachesParTache = null, bool $estEnseignant = true): array
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
}
