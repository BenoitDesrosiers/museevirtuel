<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCoursRequest;
use App\Http\Requests\UpdateCoursRequest;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use App\Models\VisioConference;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CoursController extends Controller
{
    /**
     * Affiche la liste des cours dans lesquels l'étudiant authentifié est inscrit.
     * Les cours verrouillés par l'enseignant sont masqués.
     * Inclut également les prochaines visioconférences planifiées pour l'étudiant.
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
            ->flatMap(function (Groupe $groupe): \Illuminate\Support\Collection {
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

        $typesProjets = TypeProjet::where('cours_id', $cours->id)
            ->with(['grille:id,type_projet_id,nom', 'sections'])
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
            'typesProjets' => $typesProjets->map(fn (TypeProjet $tp) => [
                'id' => $tp->id,
                'nom' => $tp->nom,
                'description' => $tp->description,
                'accessible' => $tp->accessible,
                'grille' => $tp->grille ? ['id' => $tp->grille->id, 'nom' => $tp->grille->nom] : null,
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
     * La validation et l'autorisation sont déléguées à StoreCoursRequest.
     */
    public function store(StoreCoursRequest $request): RedirectResponse
    {
        auth()->user()->cours()->create($request->validated());

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
