<?php

namespace App\Http\Controllers;

use App\Enums\TypeCours;
use App\Http\Requests\StoreCoursRequest;
use App\Http\Requests\UpdateCoursRequest;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use App\Models\VisioConference;
use App\Services\AppliquerGabaritService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class CoursController extends Controller
{
    /**
     * Affiche la liste des cours dans lesquels l'étudiant authentifié est inscrit.
     * Les cours verrouillés par l'enseignant sont masqués.
     * Inclut les prochaines visioconférences, les références personnelles et la config Zotero.
     */
    public function index(): Response
    {
        $user = auth()->user();

        $cours = $user->coursInscrits()
            ->where('is_verrouille', false)
            ->with('enseignant:id,prenom,nom')
            ->orderBy('nom_cours')
            ->get();

        $coursIds = $cours->pluck('id');
        $groupeIds = $user->groupesMembre()->pluck('groupes.id');

        // Classe (section) de l'étudiant pour chaque cours — permet de lier directement la page de classe
        $classesParCours = $user->classesInscrites()
            ->whereIn('classes.cours_id', $coursIds)
            ->get(['classes.id', 'classes.cours_id'])
            ->keyBy('cours_id');

        // Projets de l'étudiant via ses groupes, avec le TypeProjet et le chemin URL complet
        $projets = $user->groupesMembre()
            ->with([
                'classe:id,cours_id',
                'projets' => fn ($q) => $q->with('typeProjet:id,nom'),
            ])
            ->get()
            ->flatMap(function (Groupe $groupe): Collection {
                return $groupe->projets->map(fn (ProjetRecherche $projet): array => [
                    'id' => $projet->id,
                    'titre' => $projet->titre_projet,
                    'type_projet' => ['id' => $projet->typeProjet->id, 'nom' => $projet->typeProjet->nom],
                    'groupe_id' => $groupe->id,
                    'classe_id' => $groupe->classe_id,
                    'cours_id' => $groupe->classe->cours_id,
                ]);
            })
            ->values();

        // Visios des cours inscrits : ouvertes à tout le cours OU ciblées pour le groupe de l'étudiant
        $prochainesVisios = VisioConference::whereIn('cours_id', $coursIds)
            ->where(fn ($q) => $q->whereNull('groupe_id')->orWhereIn('groupe_id', $groupeIds))
            ->whereNull('ended_at')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>=', now())
            ->with(['cours:id,nom_cours', 'animateur:id,prenom,nom'])
            ->orderBy('scheduled_at')
            ->limit(10)
            ->get()
            ->map(fn (VisioConference $v) => [
                'id' => $v->id,
                'titre' => $v->titre,
                'scheduled_at' => $v->scheduled_at->toIso8601String(),
                'started_at' => $v->started_at?->toIso8601String(),
                'jitsi_room' => $v->jitsi_room,
                'cours' => ['id' => $v->cours->id, 'nom_cours' => $v->cours->nom_cours],
                'animateur' => ['prenom' => $v->animateur->prenom, 'nom' => $v->animateur->nom],
            ]);

        // Références personnelles de l'étudiant
        $mesReferences = $user->etudiantReferences()
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'titre' => $r->titre,
                'auteurs' => $r->auteurs,
                'annee' => $r->annee,
                'type_source' => $r->type_source,
                'url' => $r->url,
                'doi' => $r->doi,
                'publication' => $r->publication,
                'ordre' => $r->ordre,
                'est_depuis_zotero' => $r->estDepuisZotero(),
            ]);

        // Configuration Zotero de l'étudiant (null si non configuré)
        $zoteroConfig = $user->zoteroCredential
            ? [
                'configure' => true,
                'synchronise_le' => $user->zoteroCredential->synchronise_le?->toIso8601String(),
            ]
            : ['configure' => false, 'synchronise_le' => null];

        return Inertia::render('Cours/Index', [
            'cours' => $cours->map(fn ($c) => [
                'id' => $c->id,
                'nom_cours' => $c->nom_cours,
                'description' => $c->description,
                'code' => $c->code,
                'groupe' => $c->groupe,
                'annee' => $c->annee,
                'session' => $c->session,
                'enseignant' => [
                    'id' => $c->enseignant->id,
                    'prenom' => $c->enseignant->prenom,
                    'nom' => $c->enseignant->nom,
                ],
                'classe_id' => $classesParCours->get($c->id)?->id,
            ]),
            'projets' => $projets,
            'prochainesVisios' => $prochainesVisios,
            'mesReferences' => $mesReferences,
            'zoteroConfig' => $zoteroConfig,
        ]);
    }

    /**
     * Affiche le détail d'un cours avec ses étudiants, classes et documents.
     *
     * Accessible aux enseignants propriétaires et aux admins (CoursPolicy::view).
     */
    public function show(Cours $cours): Response
    {
        $this->authorize('view', $cours);

        $classes = $cours->classes()
            ->withCount(['etudiants', 'groupes'])
            ->orderBy('numero')
            ->get();

        $documents = $cours->documents()->get();

        $echeancierEtapes = $cours->echeancierEtapes()
            ->get()
            ->map(fn ($etape) => [
                'id' => $etape->id,
                'semaine' => $etape->semaine,
                'periode' => $etape->periode,
                'etape' => $etape->etape,
                'is_done' => $etape->is_done,
                'ordre' => $etape->ordre,
            ]);

        $objectifs = $cours->objectifs()
            ->get()
            ->map(fn ($o) => [
                'id' => $o->id,
                'contenu' => $o->contenu,
                'ordre' => $o->ordre,
            ]);

        $references = $cours->references()
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'nom' => $r->nom,
                'url' => $r->url,
                'ordre' => $r->ordre,
            ]);

        $typesProjets = TypeProjet::where('cours_id', $cours->id)
            ->with(['sections'])
            ->orderBy('nom')
            ->get();

        $visioConferences = $cours->visioConferences()
            ->with('animateur:id,prenom,nom')
            ->get()
            ->map(fn (VisioConference $v) => [
                'id' => $v->id,
                'cours_id' => $v->cours_id,
                'groupe_id' => $v->groupe_id,
                'jitsi_room' => $v->jitsi_room,
                'titre' => $v->titre,
                'scheduled_at' => $v->scheduled_at?->toIso8601String(),
                'started_at' => $v->started_at?->toIso8601String(),
                'ended_at' => $v->ended_at?->toIso8601String(),
                'recording_url' => $v->recording_url,
                'animateur' => [
                    'id' => $v->animateur->id,
                    'prenom' => $v->animateur->prenom,
                    'nom' => $v->animateur->nom,
                ],
            ]);

        return Inertia::render('Cours/Show', [
            'cours' => $cours,
            'classes' => $classes,
            'documents' => $documents,
            'echeancierEtapes' => $echeancierEtapes,
            'objectifs' => $objectifs,
            'references' => $references,
            'typesProjets' => $typesProjets->map(fn (TypeProjet $tp) => [
                'id' => $tp->id,
                'nom' => $tp->nom,
                'description' => $tp->description,
                'accessible' => $tp->accessible,
                'sections' => $tp->sections->map(fn ($s) => [
                    'id' => $s->id,
                    'label' => $s->label,
                    'description' => $s->description,
                    'ordre' => $s->ordre,
                ])->values(),
            ])->values(),
            'visioConferences' => $visioConferences,
        ]);
    }

    /**
     * Enregistre un nouveau cours pour l'enseignant authentifié.
     *
     * Si le type est 'cours_complet' et que `utiliser_gabarit` est activé,
     * le gabarit standard est automatiquement appliqué au cours créé.
     *
     * La validation et l'autorisation sont déléguées à StoreCoursRequest.
     */
    public function store(StoreCoursRequest $request, AppliquerGabaritService $gabaritService): RedirectResponse
    {
        $validated = $request->validated();

        // On extrait le flag avant la création — il ne fait pas partie du modèle
        $utiliserGabarit = (bool) ($validated['utiliser_gabarit'] ?? false);
        unset($validated['utiliser_gabarit']);

        /** @var Cours $cours */
        $cours = auth()->user()->cours()->create($validated);

        if ($utiliserGabarit && $cours->type_cours === TypeCours::CoursComplet) {
            $gabaritService->appliquer($cours, 'cours_complet');
        }

        return back()->with('success', __('cours.created'));
    }

    /**
     * Met à jour un cours existant.
     *
     * La validation et l'autorisation (CoursPolicy::update) sont déléguées à UpdateCoursRequest.
     */
    public function update(UpdateCoursRequest $request, Cours $cours): RedirectResponse
    {
        $cours->update($request->validated());

        return back()->with('success', __('cours.updated'));
    }

    /**
     * Inverse le statut de verrouillage d'un cours.
     *
     * Un cours verrouillé n'est plus visible pour les étudiants.
     *
     * @throws AuthorizationException
     */
    public function toggleVerrouillage(Cours $cours): RedirectResponse
    {
        $this->authorize('update', $cours);

        $verrouille = ! $cours->is_verrouille;
        $cours->update(['is_verrouille' => $verrouille]);

        $message = $verrouille ? __('cours.verrouille') : __('cours.deverrouille');

        return back()->with('success', $message);
    }

    /**
     * Supprime un cours et toutes ses données associées.
     *
     * @throws AuthorizationException
     */
    public function destroy(Cours $cours): RedirectResponse
    {
        $this->authorize('delete', $cours);

        $cours->delete();

        return to_route('enseignant.index')->with('success', __('cours.deleted'));
    }
}
