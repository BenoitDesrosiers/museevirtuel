<?php

namespace App\Http\Controllers;

use App\Actions\ExportProjetPdf;
use App\Actions\ExportProjetWord;
use App\Helpers\HtmlHelper;
use App\Http\Requests\UpsertProjetCommentaireRequest;
use App\Http\Requests\UpsertProjetNoteRequest;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\EntrevueConcept;
use App\Models\GrilleCorrection;
use App\Models\Groupe;
use App\Models\ProjetAnnotation;
use App\Models\ProjetCommentaire;
use App\Models\ProjetConclusion;
use App\Models\ProjetDeveloppement;
use App\Models\ProjetGrilleMalus;
use App\Models\ProjetGrilleNote;
use App\Models\ProjetNote;
use App\Models\ProjetRecherche;
use App\Models\ProjetSectionContenu;
use App\Models\ProjetSectionParagraphe;
use App\Models\ProjetVoteRemise;
use App\Models\TypeProjet;
use App\Models\TypeProjetSection;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProjetRechercheController extends Controller
{
    /** Pattern regex validant les noms de champs annotables (développement_{id}, section_{id} ou section_paragraphe_{id}). */
    private const CHAMP_ANNOTABLE_REGEX = '/^(developpement_\d+|section_\d+|section_paragraphe_\d+)$/';

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
        $estEnseignant = $cours->enseignant_id === $user->id;

        // Charger les TypeProjets de l'enseignant du cours
        $query = TypeProjet::where('enseignant_id', $cours->enseignant_id);

        // Les étudiants ne voient que les types rendus accessibles par l'enseignant
        if (! $estEnseignant && $user->role !== 'admin') {
            $query->where('accessible', true);
        }

        $typesProjets = $query->get();

        // Précharger tous les projets de ce groupe en une seule requête — évite le N+1
        $projetsParType = ProjetRecherche::where('groupe_id', $groupe->id)
            ->whereIn('type_projet_id', $typesProjets->pluck('id'))
            ->with('conclusions')
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
                'typeProjet' => $typeProjet->only('id', 'nom', 'description'),
                'projet' => $projet
                    ? ['id' => $projet->id, 'titre_projet' => $projet->titre_projet, 'completion' => $projet->completion()]
                    : null,
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
        $estEnseignant = $cours->enseignant_id === $user->id;

        // Guard accessibilité : si le type de projet n'est pas accessible, les étudiants ne peuvent pas accéder
        if (! $estEnseignant && $user->role !== 'admin') {
            abort_if(! $typeProjet->accessible, 403, 'Ce type de projet n\'est pas encore accessible.');
        }

        // Créer le projet partagé s'il n'existe pas encore (accès à l'éditeur implique volonté de créer)
        $projet = ProjetRecherche::firstOrCreate([
            'groupe_id' => $groupe->id,
            'type_projet_id' => $typeProjet->id,
        ]);

        // Précharger en une seule requête chacune des relations — évite le N+1
        $projet->load(['conclusions', 'commentaires', 'annotations', 'developpements', 'votes', 'notes', 'notesGrille.critere', 'malusAppliques.malus', 'typeProjet.grille.criteres', 'typeProjet.grille.malus', 'typeProjet.sections', 'sectionContenus', 'sectionParagraphes', 'entrevueConcepts.lignes']);

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

        // Pour les étudiants, masquer les corrections si correction_visible = false
        $annotationsFiltrees = $estEnseignant
            ? $projet->annotations
            : $projet->annotations->when(
                ! $projet->correction_visible,
                fn ($coll) => $coll->where('type', 'commentaire')
            );

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
                        'type' => $a->type,
                        'user_id' => $a->user_id,
                    ])
                    ->values();
            });

        $estMembre = ! $estEnseignant && $groupe->membres->contains('id', $user->id);

        // Condition commune : membre + non verrouillé + remise encore possible
        $peutAgir = $estMembre && ! $projet->verrouille && $projet->peutEtreRemis();

        // Notes standard pondérées (visibles selon correction_visible)
        $noteFinaleParEtudiant = ($estEnseignant || $projet->correction_visible)
            ? $groupe->membres->mapWithKeys(fn (User $membre) => [$membre->id => ProjetNote::noteFinale($projet, $membre)])
            : $groupe->membres->mapWithKeys(fn (User $membre) => [$membre->id => null]);

        // Grille de correction personnalisée : dérivée du type de projet associé
        $grillePersonnalisee = $projet->typeProjet?->grille;
        $notesGrilleParEtudiant = [];
        $malusParEtudiant = [];
        $noteFinaleGrilleParEtudiant = [];

        if ($grillePersonnalisee) {
            if ($estEnseignant || $projet->correction_visible) {
                $notesGrilleParEtudiant = $projet->notesGrille
                    ->groupBy('user_id')
                    ->map(fn ($notes) => $notes->keyBy('critere_id')->map(fn (ProjetGrilleNote $n) => $n->note));

                $malusParEtudiant = $projet->malusAppliques
                    ->groupBy('user_id')
                    ->map(fn ($malus) => $malus->keyBy('malus_id')->map(fn (ProjetGrilleMalus $m) => (bool) $m->applique));

                $noteFinaleGrilleParEtudiant = $groupe->membres->mapWithKeys(
                    fn (User $membre) => [$membre->id => ProjetGrilleNote::noteFinale($projet, $membre)]
                );
            } else {
                $noteFinaleGrilleParEtudiant = $groupe->membres->mapWithKeys(fn (User $membre) => [$membre->id => null]);
            }
        }

        return Inertia::render('Projets/Show', [
            'groupe' => $groupe,
            'classe' => $classe->only('id', 'code', 'cours_id'),
            'cours' => $cours->only('id', 'nom_cours', 'code', 'groupe'),
            'enseignant' => $cours->enseignant->only('id', 'prenom', 'nom'),
            'membres' => $groupe->membres->map->only('id', 'prenom', 'nom')->values(),
            'projet' => $projet,
            'typeProjet' => $typeProjet->only('id', 'nom'),
            'developpements' => $projet->developpements->map->only('id', 'ordre', 'titre', 'contenu')->values(),
            'conclusions' => $conclusions,
            'peutEditer' => $peutAgir,
            'estEnseignant' => $estEnseignant,
            'correctionVisible' => (bool) $projet->correction_visible,
            'verrouille' => (bool) $projet->verrouille,
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
            'noteFinaleParEtudiant' => $noteFinaleParEtudiant,
            'grillePersonnalisee' => $grillePersonnalisee,
            'notesGrilleParEtudiant' => $notesGrilleParEtudiant,
            'malusParEtudiant' => $malusParEtudiant,
            'noteFinaleGrilleParEtudiant' => $noteFinaleGrilleParEtudiant,
            'sections' => $this->construireSections($projet, $groupe->membres),
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
        $estEnseignant = $cours->enseignant_id === $user->id;

        $projet = ProjetRecherche::where('groupe_id', $groupe->id)
            ->where('type_projet_id', $typeProjet->id)
            ->with(['typeProjet.sections', 'sectionContenus', 'sectionParagraphes', 'conclusions', 'entrevueConcepts.lignes'])
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
            'thematiques' => $groupe->thematiques->map->only('id', 'nom'),
            'membres' => $groupe->membres->map->only('id', 'prenom', 'nom')->values(),
            'projet' => $projet
                ? ['id' => $projet->id, 'titre_projet' => $projet->titre_projet]
                : null,
            'sections' => $sections,
            'estEnseignant' => $estEnseignant,
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
        abort_if($classe->cours_id !== $cours->id, 404);
        abort_if($groupe->classe_id !== $classe->id, 404);
        $this->verifierTypeProjetAppartientCours($typeProjet, $cours);
        $groupe->loadMissing('classe.cours');
        $this->authorize('manageThematiques', $groupe);

        // Vérifier que la section appartient au TypeProjet passé en URL — évite l'IDOR
        abort_if($section->type_projet_id !== $typeProjet->id, 404);

        $projet = $this->trouverProjet($groupe, $typeProjet);

        abort_if($projet->verrouille, 403, 'Ce document est verrouillé.');
        abort_if(! $projet->peutEtreRemis(), 422, 'Ce travail a déjà été remis.');

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
     * Met à jour le titre du projet.
     *
     * @throws HttpException
     */
    public function update(Request $request, Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet): JsonResponse
    {
        abort_if($classe->cours_id !== $cours->id, 404);
        abort_if($groupe->classe_id !== $classe->id, 404);
        $this->verifierTypeProjetAppartientCours($typeProjet, $cours);

        $groupe->loadMissing('classe.cours');
        $this->authorize('manageThematiques', $groupe);

        $validated = $request->validate([
            'titre_projet' => ['nullable', 'string', 'max:500'],
        ]);

        $existant = ProjetRecherche::where('groupe_id', $groupe->id)
            ->where('type_projet_id', $typeProjet->id)
            ->first();

        abort_if($existant?->verrouille, 403, 'Ce document est verrouillé.');
        abort_if($existant !== null && ! $existant->peutEtreRemis(), 422, 'Ce travail a déjà été remis.');

        $projet = ProjetRecherche::updateOrCreate(
            ['groupe_id' => $groupe->id, 'type_projet_id' => $typeProjet->id],
            $validated,
        );

        return response()->json([
            'message' => 'saved',
            'completion' => $projet->completion(),
        ]);
    }

    /**
     * Ajoute un nouveau paragraphe de développement à la fin de la liste.
     *
     * @throws HttpException
     */
    public function storeDeveloppement(Request $request, Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet): JsonResponse
    {
        $this->verifierTypeProjetAppartientCours($typeProjet, $cours);
        $this->autoriserMembreGroupe($cours, $classe, $groupe);

        $projet = ProjetRecherche::firstOrCreate([
            'groupe_id' => $groupe->id,
            'type_projet_id' => $typeProjet->id,
        ]);

        abort_if($projet->verrouille, 403, 'Ce document est verrouillé.');
        abort_if(! $projet->peutEtreRemis(), 422, 'Ce travail a déjà été remis.');

        $ordre = ($projet->developpements()->max('ordre') ?? 0) + 1;

        $dev = ProjetDeveloppement::create([
            'projet_id' => $projet->id,
            'ordre' => $ordre,
            'titre' => null,
            'contenu' => null,
        ]);

        return response()->json([
            'message' => 'created',
            'developpement' => $dev->only('id', 'ordre', 'titre', 'contenu'),
            'completion' => $projet->completion(),
        ], 201);
    }

    /**
     * Met à jour le titre et/ou le contenu d'un paragraphe de développement.
     *
     * @throws HttpException
     */
    public function updateDeveloppement(Request $request, Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, ProjetDeveloppement $developpement): JsonResponse
    {
        $this->verifierTypeProjetAppartientCours($typeProjet, $cours);
        $this->autoriserMembreGroupe($cours, $classe, $groupe);

        $projet = $this->trouverProjet($groupe, $typeProjet);

        abort_if($developpement->projet_id !== $projet->id, 404);
        abort_if($projet->verrouille, 403, 'Ce document est verrouillé.');
        abort_if(! $projet->peutEtreRemis(), 422, 'Ce travail a déjà été remis.');

        $validated = $request->validate([
            'titre' => ['nullable', 'string', 'max:500'],
            'contenu' => ['nullable', 'string'],
        ]);

        $developpement->update($validated);

        if (array_key_exists('contenu', $validated) && $validated['contenu'] !== null) {
            $this->supprimerAnnotationsOrphelines(
                $projet,
                'developpement_'.$developpement->id,
                $validated['contenu']
            );
        }

        return response()->json([
            'message' => 'saved',
            'completion' => $projet->completion(),
        ]);
    }

    /**
     * Supprime un paragraphe de développement et réordonne les suivants.
     *
     * Refuse la suppression si c'est le dernier paragraphe (minimum : 1).
     *
     * @throws HttpException
     */
    public function destroyDeveloppement(Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, ProjetDeveloppement $developpement): JsonResponse
    {
        $this->verifierTypeProjetAppartientCours($typeProjet, $cours);
        $this->autoriserMembreGroupe($cours, $classe, $groupe);

        $projet = $this->trouverProjet($groupe, $typeProjet);

        abort_if($developpement->projet_id !== $projet->id, 404);
        abort_if($projet->verrouille, 403, 'Ce document est verrouillé.');
        abort_if(! $projet->peutEtreRemis(), 422, 'Ce travail a déjà été remis.');
        abort_if($projet->developpements()->count() <= 1, 422, 'Le projet doit conserver au moins un paragraphe.');

        $developpement->delete();

        $projet->developpements()->orderBy('ordre')->each(
            function (ProjetDeveloppement $dev, int $index): void {
                $dev->update(['ordre' => $index + 1]);
            }
        );

        return response()->json([
            'message' => 'deleted',
            'completion' => $projet->completion(),
        ]);
    }

    /**
     * Met à jour l'ordre de tous les paragraphes de développement d'un projet.
     *
     * @throws HttpException
     */
    public function reorderDeveloppements(Request $request, Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet): JsonResponse
    {
        $this->verifierTypeProjetAppartientCours($typeProjet, $cours);
        $this->autoriserMembreGroupe($cours, $classe, $groupe);

        $projet = $this->trouverProjet($groupe, $typeProjet);

        abort_if($projet->verrouille, 403, 'Ce document est verrouillé.');

        $validated = $request->validate([
            'ordre' => ['required', 'array'],
            'ordre.*' => ['required', 'integer', 'exists:projet_developpements,id'],
        ]);

        foreach ($validated['ordre'] as $index => $id) {
            ProjetDeveloppement::where('id', $id)
                ->where('projet_id', $projet->id)
                ->update(['ordre' => $index + 1]);
        }

        return response()->json(['message' => 'reordered']);
    }

    /**
     * Ajoute un nouveau paragraphe à la fin d'une section de type 'paragraphes'.
     *
     * @throws HttpException
     */
    public function storeSectionParagraphe(Request $request, Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, TypeProjetSection $section): JsonResponse
    {
        $this->verifierTypeProjetAppartientCours($typeProjet, $cours);
        $this->autoriserMembreGroupe($cours, $classe, $groupe);
        abort_if($section->type_projet_id !== $typeProjet->id, 404);

        $projet = ProjetRecherche::firstOrCreate([
            'groupe_id' => $groupe->id,
            'type_projet_id' => $typeProjet->id,
        ]);

        abort_if($projet->verrouille, 403, 'Ce document est verrouillé.');
        abort_if(! $projet->peutEtreRemis(), 422, 'Ce travail a déjà été remis.');

        $ordre = (ProjetSectionParagraphe::where('projet_id', $projet->id)
            ->where('section_id', $section->id)
            ->max('ordre') ?? 0) + 1;

        $paragraphe = ProjetSectionParagraphe::create([
            'projet_id' => $projet->id,
            'section_id' => $section->id,
            'ordre' => $ordre,
            'titre' => null,
            'contenu' => null,
        ]);

        return response()->json([
            'message' => 'created',
            'paragraphe' => $paragraphe->only('id', 'ordre', 'titre', 'contenu'),
        ], 201);
    }

    /**
     * Met à jour le titre et/ou le contenu d'un paragraphe de section.
     *
     * @throws HttpException
     */
    public function updateSectionParagraphe(Request $request, Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, TypeProjetSection $section, ProjetSectionParagraphe $paragraphe): JsonResponse
    {
        $this->verifierTypeProjetAppartientCours($typeProjet, $cours);
        $this->autoriserMembreGroupe($cours, $classe, $groupe);
        abort_if($section->type_projet_id !== $typeProjet->id, 404);

        $projet = $this->trouverProjet($groupe, $typeProjet);

        abort_if($paragraphe->projet_id !== $projet->id || $paragraphe->section_id !== $section->id, 404);
        abort_if($projet->verrouille, 403, 'Ce document est verrouillé.');
        abort_if(! $projet->peutEtreRemis(), 422, 'Ce travail a déjà été remis.');

        $validated = $request->validate([
            'titre' => ['nullable', 'string', 'max:500'],
            'contenu' => ['nullable', 'string'],
        ]);

        $paragraphe->update($validated);

        if (array_key_exists('contenu', $validated) && $validated['contenu'] !== null) {
            $this->supprimerAnnotationsOrphelines(
                $projet,
                'section_paragraphe_'.$paragraphe->id,
                $validated['contenu']
            );
        }

        return response()->json(['message' => 'saved']);
    }

    /**
     * Supprime un paragraphe de section et réordonne les suivants.
     *
     * Refuse la suppression si c'est le dernier paragraphe (minimum : 1).
     *
     * @throws HttpException
     */
    public function destroySectionParagraphe(Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, TypeProjetSection $section, ProjetSectionParagraphe $paragraphe): JsonResponse
    {
        $this->verifierTypeProjetAppartientCours($typeProjet, $cours);
        $this->autoriserMembreGroupe($cours, $classe, $groupe);
        abort_if($section->type_projet_id !== $typeProjet->id, 404);

        $projet = $this->trouverProjet($groupe, $typeProjet);

        abort_if($paragraphe->projet_id !== $projet->id || $paragraphe->section_id !== $section->id, 404);
        abort_if($projet->verrouille, 403, 'Ce document est verrouillé.');
        abort_if(! $projet->peutEtreRemis(), 422, 'Ce travail a déjà été remis.');

        $count = ProjetSectionParagraphe::where('projet_id', $projet->id)
            ->where('section_id', $section->id)
            ->count();

        abort_if($count <= 1, 422, 'La section doit conserver au moins un paragraphe.');

        $paragraphe->delete();

        ProjetSectionParagraphe::where('projet_id', $projet->id)
            ->where('section_id', $section->id)
            ->orderBy('ordre')
            ->each(function (ProjetSectionParagraphe $p, int $index): void {
                $p->update(['ordre' => $index + 1]);
            });

        return response()->json(['message' => 'deleted']);
    }

    /**
     * Met à jour l'ordre de tous les paragraphes d'une section de type 'paragraphes'.
     *
     * @throws HttpException
     */
    public function reorderSectionParagraphes(Request $request, Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, TypeProjetSection $section): JsonResponse
    {
        $this->verifierTypeProjetAppartientCours($typeProjet, $cours);
        $this->autoriserMembreGroupe($cours, $classe, $groupe);
        abort_if($section->type_projet_id !== $typeProjet->id, 404);

        $projet = $this->trouverProjet($groupe, $typeProjet);

        abort_if($projet->verrouille, 403, 'Ce document est verrouillé.');

        $validated = $request->validate([
            'ordre' => ['required', 'array'],
            'ordre.*' => ['required', 'integer', 'exists:projet_section_paragraphes,id'],
        ]);

        foreach ($validated['ordre'] as $index => $id) {
            ProjetSectionParagraphe::where('id', $id)
                ->where('projet_id', $projet->id)
                ->where('section_id', $section->id)
                ->update(['ordre' => $index + 1]);
        }

        return response()->json(['message' => 'reordered']);
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
        abort_if($classe->cours_id !== $cours->id, 404);
        abort_if($groupe->classe_id !== $classe->id, 404);
        $this->verifierTypeProjetAppartientCours($typeProjet, $cours);

        $groupe->load(['classe.cours', 'membres']);
        $this->authorize('manageThematiques', $groupe);

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

        abort_if($projet->verrouille, 403, 'Ce document est verrouillé.');

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
     * Crée ou met à jour le commentaire de l'enseignant pour un champ donné.
     *
     * @throws HttpException
     */
    public function upsertCommentaire(UpsertProjetCommentaireRequest $request, Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet): JsonResponse
    {
        $this->verifierTypeProjetAppartientCours($typeProjet, $cours);
        $this->autoriserEnseignant($cours, $classe, $groupe);

        $projet = ProjetRecherche::firstOrCreate([
            'groupe_id' => $groupe->id,
            'type_projet_id' => $typeProjet->id,
        ]);

        $commentaire = ProjetCommentaire::updateOrCreate(
            ['projet_id' => $projet->id, 'champ' => $request->validated('champ')],
            ['contenu' => $request->validated('contenu'), 'created_by' => auth()->id()],
        );

        return response()->json([
            'message' => 'saved',
            'id' => $commentaire->id,
            'contenu' => $commentaire->contenu,
        ]);
    }

    /**
     * Supprime un commentaire de l'enseignant.
     *
     * @throws HttpException
     */
    public function destroyCommentaire(Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, ProjetCommentaire $commentaire): JsonResponse
    {
        $this->verifierTypeProjetAppartientCours($typeProjet, $cours);
        $this->autoriserEnseignant($cours, $classe, $groupe);

        $projet = $this->trouverProjet($groupe, $typeProjet);

        abort_if($commentaire->projet_id !== $projet->id, 404);
        $commentaire->delete();

        return response()->json(['message' => 'deleted']);
    }

    /**
     * Crée ou met à jour la note d'un critère de la grille de correction.
     *
     * @throws HttpException
     */
    public function upsertNote(UpsertProjetNoteRequest $request, Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet): JsonResponse
    {
        $this->verifierTypeProjetAppartientCours($typeProjet, $cours);
        $this->autoriserEnseignant($cours, $classe, $groupe);

        $groupe->loadMissing('membres');
        abort_unless(
            $groupe->membres->contains('id', $request->validated('user_id')),
            422,
            'Cet étudiant n\'est pas membre de ce groupe.',
        );

        $projet = ProjetRecherche::firstOrCreate([
            'groupe_id' => $groupe->id,
            'type_projet_id' => $typeProjet->id,
        ]);

        ProjetNote::updateOrCreate(
            [
                'projet_id' => $projet->id,
                'user_id' => $request->validated('user_id'),
                'critere' => $request->validated('critere'),
            ],
            ['note' => $request->validated('note')],
        );

        $projet->load('notes');
        $groupe->load('membres');

        return response()->json([
            'message' => 'saved',
            'noteFinaleParEtudiant' => $groupe->membres->mapWithKeys(
                fn (User $membre) => [$membre->id => ProjetNote::noteFinale($projet, $membre)]
            ),
        ]);
    }

    /**
     * Crée ou met à jour une annotation inline sur un champ du projet.
     *
     * @throws HttpException si l'utilisateur n'est pas l'enseignant du cours
     */
    public function upsertAnnotation(Request $request, Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet): JsonResponse
    {
        $this->verifierTypeProjetAppartientCours($typeProjet, $cours);
        $this->autoriserEnseignant($cours, $classe, $groupe);

        $validated = $request->validate([
            'champ' => ['required', 'string', 'regex:'.self::CHAMP_ANNOTABLE_REGEX],
            'commentaire_id' => ['required', 'string', 'max:36'],
            'contenu' => ['required', 'string', 'max:1000'],
            'html' => ['required', 'string'],
            'type' => ['sometimes', 'string', 'in:commentaire,correction'],
        ]);

        $projet = ProjetRecherche::firstOrCreate([
            'groupe_id' => $groupe->id,
            'type_projet_id' => $typeProjet->id,
        ]);

        $this->mettreAJourChampHtml($projet, $validated['champ'], $validated['html']);
        $this->supprimerAnnotationsOrphelines($projet, $validated['champ'], $validated['html']);

        preg_match_all('/data-comment-id="([^"]+)"/', $validated['html'], $allIds);
        $positionIndex = array_search($validated['commentaire_id'], $allIds[1], true);
        $position = $positionIndex !== false ? (int) $positionIndex : null;

        preg_match(
            '/<mark[^>]*data-comment-id="'.preg_quote($validated['commentaire_id'], '/').'[^>]*"[^>]*>(.*?)<\/mark>/si',
            $validated['html'],
            $markMatch
        );
        $motAnnote = isset($markMatch[1]) ? strip_tags($markMatch[1]) : null;

        $annotation = ProjetAnnotation::updateOrCreate(
            ['projet_id' => $projet->id, 'commentaire_id' => $validated['commentaire_id']],
            [
                'champ' => $validated['champ'],
                'contenu' => $validated['contenu'],
                'type' => $validated['type'] ?? 'commentaire',
                'position' => $position,
                'mot_annote' => $motAnnote,
                'user_id' => auth()->id(),
            ]
        );

        return response()->json([
            'message' => 'saved',
            'id' => $annotation->id,
            'commentaire_id' => $annotation->commentaire_id,
            'contenu' => $annotation->contenu,
            'type' => $annotation->type,
            'user_id' => $annotation->user_id,
        ]);
    }

    /**
     * Supprime une annotation inline et met à jour le HTML du champ pour retirer la marque.
     *
     * @throws HttpException
     */
    public function destroyAnnotation(Request $request, Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, ProjetAnnotation $annotation): JsonResponse
    {
        $this->verifierTypeProjetAppartientCours($typeProjet, $cours);
        $this->autoriserEnseignant($cours, $classe, $groupe);

        $projet = $this->trouverProjet($groupe, $typeProjet);

        abort_if($annotation->projet_id !== $projet->id, 404);

        $validated = $request->validate([
            'champ' => ['required', 'string', 'regex:'.self::CHAMP_ANNOTABLE_REGEX],
            'html' => ['required', 'string'],
        ]);

        $this->mettreAJourChampHtml($projet, $validated['champ'], $validated['html']);
        $annotation->delete();

        return response()->json(['message' => 'deleted']);
    }

    /**
     * Enregistre la remise du travail par l'équipe d'étudiants.
     *
     * @throws HttpException
     */
    public function remettreTravail(Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet): JsonResponse
    {
        abort_if($classe->cours_id !== $cours->id, 404);
        abort_if($groupe->classe_id !== $classe->id, 404);
        $this->verifierTypeProjetAppartientCours($typeProjet, $cours);

        $groupe->loadMissing('membres');
        abort_unless($groupe->membres->contains('id', auth()->id()), 403);

        $projet = ProjetRecherche::firstOrCreate([
            'groupe_id' => $groupe->id,
            'type_projet_id' => $typeProjet->id,
        ]);

        // Associer la relation pour que peutEtreRemis() lise les paramètres depuis TypeProjet
        $projet->setRelation('typeProjet', $typeProjet);

        abort_if($projet->verrouille, 403, 'Ce document est verrouillé.');
        abort_unless($projet->peutEtreRemis(), 422, 'Ce travail a déjà été remis et les remises multiples ne sont pas autorisées.');

        $projet->update(['remis_le' => now()]);

        return response()->json([
            'message' => 'remis',
            'remis_le' => $projet->remis_le->toIso8601String(),
        ]);
    }

    /**
     * Annule la remise du travail (enseignant seulement).
     *
     * @throws HttpException
     */
    public function annulerRemise(Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet): JsonResponse
    {
        $this->verifierTypeProjetAppartientCours($typeProjet, $cours);
        $this->autoriserEnseignant($cours, $classe, $groupe);

        $projet = $this->trouverProjet($groupe, $typeProjet);

        DB::transaction(function () use ($projet): void {
            $projet->votes()->delete();
            $projet->update(['remis_le' => null]);
        });

        return response()->json(['message' => 'remise_annulee']);
    }

    /**
     * Enregistre ou met à jour le vote de remise d'un étudiant membre du groupe.
     *
     * Si tous les membres ont voté true, la remise est enregistrée de façon atomique.
     *
     * @throws HttpException
     */
    public function voterRemise(Request $request, Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet): JsonResponse
    {
        abort_if($classe->cours_id !== $cours->id, 404);
        abort_if($groupe->classe_id !== $classe->id, 404);
        $this->verifierTypeProjetAppartientCours($typeProjet, $cours);

        $groupe->loadMissing('membres');
        abort_unless($groupe->membres->contains('id', auth()->id()), 403);

        $projet = $this->trouverProjet($groupe, $typeProjet);

        abort_unless($projet->peutEtreRemis(), 422, 'La remise n\'est plus possible.');

        $validated = $request->validate([
            'vote' => ['required', 'boolean'],
        ]);

        ProjetVoteRemise::updateOrCreate(
            ['projet_id' => $projet->id, 'user_id' => auth()->id()],
            ['vote' => $validated['vote']],
        );

        $votes = $projet->votes()->get();
        $nbMembres = $groupe->membres->count();

        $tousOntVote = $votes->count() === $nbMembres
            && $votes->every(fn (ProjetVoteRemise $v) => $v->vote);

        if ($tousOntVote) {
            DB::transaction(function () use ($projet): void {
                $projet->refresh();

                if ($projet->remis_le === null || $projet->remises_multiples) {
                    $projet->update(['remis_le' => now()]);
                }
            });
        }

        return response()->json([
            'message' => 'vote_enregistre',
            'remis_le' => $projet->fresh()->remis_le?->toIso8601String(),
        ]);
    }

    /**
     * Active ou désactive la visibilité des corrections pour les étudiants.
     *
     * @throws HttpException
     */
    public function toggleCorrectionVisible(Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet): JsonResponse
    {
        $this->verifierTypeProjetAppartientCours($typeProjet, $cours);
        $this->autoriserEnseignant($cours, $classe, $groupe);

        $projet = ProjetRecherche::firstOrCreate([
            'groupe_id' => $groupe->id,
            'type_projet_id' => $typeProjet->id,
        ]);

        $projet->update(['correction_visible' => ! $projet->correction_visible]);

        return response()->json([
            'message' => 'toggled',
            'correction_visible' => (bool) $projet->correction_visible,
        ]);
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
     * Crée ou met à jour la note d'un étudiant pour un critère de la grille personnalisée.
     *
     * @throws HttpException
     */
    public function upsertNoteGrille(Request $request, Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet): JsonResponse
    {
        $this->verifierTypeProjetAppartientCours($typeProjet, $cours);
        $this->autoriserEnseignant($cours, $classe, $groupe);

        $validated = $request->validate([
            'critere_id' => ['required', 'integer'],
            'note' => ['required', 'integer', Rule::in([0, 2, 3, 4])],
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        [$grille, $projet] = $this->chargerContexteGrille($cours, $classe, $groupe, $typeProjet, $validated['user_id']);

        $request->validate([
            'critere_id' => [Rule::exists('grille_criteres', 'id')->where('grille_id', $grille->id)],
        ]);

        ProjetGrilleNote::updateOrCreate(
            [
                'projet_id' => $projet->id,
                'user_id' => $validated['user_id'],
                'critere_id' => $validated['critere_id'],
            ],
            ['note' => $validated['note']],
        );

        return $this->reponseGrilleNotesFinales($projet, $groupe);
    }

    /**
     * Applique ou retire un malus sur un étudiant pour la grille personnalisée du projet.
     *
     * @throws HttpException
     */
    public function toggleMalusGrille(Request $request, Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet): JsonResponse
    {
        $this->verifierTypeProjetAppartientCours($typeProjet, $cours);
        $this->autoriserEnseignant($cours, $classe, $groupe);

        $validated = $request->validate([
            'malus_id' => ['required', 'integer'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'applique' => ['required', 'boolean'],
        ]);

        [$grille, $projet] = $this->chargerContexteGrille($cours, $classe, $groupe, $typeProjet, $validated['user_id']);

        $request->validate([
            'malus_id' => [Rule::exists('grille_malus', 'id')->where('grille_id', $grille->id)],
        ]);

        ProjetGrilleMalus::updateOrCreate(
            [
                'projet_id' => $projet->id,
                'user_id' => $validated['user_id'],
                'malus_id' => $validated['malus_id'],
            ],
            ['applique' => $validated['applique']],
        );

        return $this->reponseGrilleNotesFinales($projet, $groupe);
    }

    /**
     * Génère et retourne le projet en PDF.
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function exportPdf(Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet): HttpResponse
    {
        $projet = $this->chargerProjetPourExport($cours, $classe, $groupe, $typeProjet);

        return (new ExportProjetPdf)->execute($projet, $groupe);
    }

    /**
     * Génère et retourne le projet en Word (.docx).
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function exportWord(Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet): StreamedResponse
    {
        $projet = $this->chargerProjetPourExport($cours, $classe, $groupe, $typeProjet);

        return (new ExportProjetWord)->execute($projet, $groupe);
    }

    /**
     * Exporte les notes finales des membres du groupe en XML.
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function exportXmlNotes(Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet): HttpResponse
    {
        abort_if($classe->cours_id !== $cours->id, 404);
        abort_if($groupe->classe_id !== $classe->id, 404);
        $this->verifierTypeProjetAppartientCours($typeProjet, $cours);

        $groupe->load(['membres', 'classe.cours']);
        $this->authorize('view', $groupe);

        $user = auth()->user();
        abort_unless(
            $user->role === 'admin' || $cours->enseignant_id === $user->id,
            403,
        );

        $projet = $this->trouverProjet($groupe, $typeProjet);

        $projet->load('notes');

        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><notes/>');

        foreach ($groupe->membres as $membre) {
            $note = ProjetNote::noteFinale($projet, $membre);
            $etudiantNode = $xml->addChild('etudiant');
            $etudiantNode->addChild('no_da', preg_replace('/\D/', '', (string) $membre->no_da));
            $etudiantNode->addChild('note', $note !== null ? (string) $note : '');
        }

        $nomFichier = sprintf('notes_groupe_%d.xml', $groupe->numero);

        return response($xml->asXML(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$nomFichier}\"",
        ]);
    }

    // ─── Méthodes privées ─────────────────────────────────────────────────────

    /**
     * Retourne le ProjetRecherche correspondant au groupe et au type de projet, ou lève une 404.
     *
     * Charge toujours la relation typeProjet pour que peutEtreRemis() lise
     * les paramètres depuis le TypeProjet sans requête supplémentaire.
     *
     * @throws HttpException
     */
    private function trouverProjet(Groupe $groupe, TypeProjet $typeProjet): ProjetRecherche
    {
        return ProjetRecherche::where('groupe_id', $groupe->id)
            ->where('type_projet_id', $typeProjet->id)
            ->with('typeProjet')
            ->firstOrFail();
    }

    /**
     * Autorise l'accès et charge le projet pour les exports PDF et Word.
     *
     * Factorise le guard commun (404/autorisation) et l'eager load partagé
     * par exportPdf et exportWord.
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    private function chargerProjetPourExport(Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet): ProjetRecherche
    {
        abort_if($classe->cours_id !== $cours->id, 404);
        abort_if($groupe->classe_id !== $classe->id, 404);
        $this->verifierTypeProjetAppartientCours($typeProjet, $cours);

        $groupe->load(['membres', 'thematiques', 'classe.cours.enseignant']);
        $this->authorize('view', $groupe);

        $projet = $this->trouverProjet($groupe, $typeProjet);
        $projet->load(['conclusions.etudiant', 'developpements']);

        return $projet;
    }

    /**
     * Vérifie que le TypeProjet appartient à l'enseignant du cours.
     *
     * Empêche l'accès à un TypeProjet d'un autre enseignant via manipulation d'URL (IDOR).
     *
     * @throws HttpException
     */
    private function verifierTypeProjetAppartientCours(TypeProjet $typeProjet, Cours $cours): void
    {
        abort_if($typeProjet->enseignant_id !== $cours->enseignant_id, 404);
    }

    /**
     * Charge la grille du type de projet et valide que l'utilisateur est bien membre du groupe.
     *
     * @return array{0: GrilleCorrection, 1: ProjetRecherche}
     *
     * @throws HttpException
     */
    private function chargerContexteGrille(Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, int $userId): array
    {
        $projet = $this->trouverProjet($groupe, $typeProjet);

        $projet->load('typeProjet.grille');
        $grille = $projet->typeProjet?->grille;
        abort_if($grille === null, 422, 'Aucune grille personnalisée n\'est définie pour ce type de projet.');

        $groupe->loadMissing('membres');
        abort_unless(
            $groupe->membres->contains('id', $userId),
            422,
            'Cet étudiant n\'est pas membre de ce groupe.',
        );

        return [$grille, $projet];
    }

    /**
     * Recharge les notes et malus d'un projet et retourne la réponse JSON standard
     * pour les endpoints de notation grille.
     */
    private function reponseGrilleNotesFinales(ProjetRecherche $projet, Groupe $groupe): JsonResponse
    {
        $projet->load(['notesGrille.critere', 'malusAppliques.malus']);

        return response()->json([
            'message' => 'saved',
            'noteFinaleGrilleParEtudiant' => $groupe->membres->mapWithKeys(
                fn (User $membre) => [$membre->id => ProjetGrilleNote::noteFinale($projet, $membre)]
            ),
        ]);
    }

    /**
     * Construit le tableau des sections dynamiques avec leur contenu courant.
     *
     * Selon le type de section :
     * - 'texte'       → champ `contenu` (ProjetSectionContenu)
     * - 'paragraphes' → champ `paragraphes` (liste ProjetSectionParagraphe triée par ordre)
     * - 'individuel'  → champ `conclusionsParMembre` (1 entrée par membre du groupe)
     * - 'entrevue'    → champ `concepts` (liste EntrevueConcept avec leurs lignes)
     *
     * @param  Collection|null  $membres  membres du groupe (requis pour le type 'individuel')
     * @return array<int, array{id: int, label: string, description: string|null, ordre: int, type: string, contenu: string|null, paragraphes: array|null, conclusionsParMembre: array|null, concepts: array|null}>
     */
    private function construireSections(ProjetRecherche $projet, ?Collection $membres = null): array
    {
        $sections = $projet->typeProjet?->sections ?? collect();

        if ($sections->isEmpty()) {
            return [];
        }

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
        ])->values()->all();
    }

    /**
     * Vérifie que le groupe et la classe appartiennent au cours
     * et autorise l'action manageThematiques.
     *
     * @throws HttpException
     */
    private function autoriserMembreGroupe(Cours $cours, Classe $classe, Groupe $groupe): void
    {
        abort_if($classe->cours_id !== $cours->id, 404);
        abort_if($groupe->classe_id !== $classe->id, 404);
        $groupe->loadMissing('classe.cours');
        $this->authorize('manageThematiques', $groupe);
    }

    /**
     * Lève une exception si la classe/groupe n'appartiennent pas au cours
     * ou si l'utilisateur authentifié n'est pas l'enseignant de ce cours.
     *
     * @throws HttpException
     */
    private function autoriserEnseignant(Cours $cours, Classe $classe, Groupe $groupe): void
    {
        abort_if($classe->cours_id !== $cours->id, 404);
        abort_if($groupe->classe_id !== $classe->id, 404);
        abort_unless($cours->enseignant_id === auth()->id(), 403);
    }

    /**
     * Supprime les annotations d'un champ dont la marque n'est plus présente dans le HTML.
     */
    private function supprimerAnnotationsOrphelines(ProjetRecherche $projet, string $champ, string $html): void
    {
        preg_match_all('/data-comment-id="([^"]+)"/', $html, $matches);
        $idsPresents = $matches[1];

        ProjetAnnotation::where('projet_id', $projet->id)
            ->where('champ', $champ)
            ->when(
                ! empty($idsPresents),
                fn ($q) => $q->whereNotIn('commentaire_id', $idsPresents),
                fn ($q) => $q,
            )
            ->delete();
    }

    /**
     * Met à jour le contenu HTML d'un champ annotable.
     *
     * Supporte les préfixes : `developpement_`, `section_paragraphe_`, `section_`.
     * Tout autre préfixe est rejeté par le CHAMP_ANNOTABLE_REGEX en amont.
     *
     * @throws HttpException si la ressource n'appartient pas au projet
     */
    private function mettreAJourChampHtml(ProjetRecherche $projet, string $champ, string $html): void
    {
        if (str_starts_with($champ, 'developpement_')) {
            $devId = (int) mb_substr($champ, mb_strlen('developpement_'));
            $dev = ProjetDeveloppement::where('id', $devId)
                ->where('projet_id', $projet->id)
                ->firstOrFail();
            $dev->update(['contenu' => $html]);
        } elseif (str_starts_with($champ, 'section_paragraphe_')) {
            $paragId = (int) mb_substr($champ, mb_strlen('section_paragraphe_'));
            $paragraphe = ProjetSectionParagraphe::where('id', $paragId)
                ->where('projet_id', $projet->id)
                ->firstOrFail();
            $paragraphe->update(['contenu' => $html]);
        } elseif (str_starts_with($champ, 'section_')) {
            $sectionId = (int) mb_substr($champ, mb_strlen('section_'));
            ProjetSectionContenu::updateOrCreate(
                ['projet_id' => $projet->id, 'section_id' => $sectionId],
                ['contenu' => $html],
            );
        }
    }
}
