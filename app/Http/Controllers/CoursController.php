<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCoursRequest;
use App\Http\Requests\UpdateCoursRequest;
use App\Models\Cours;
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
     */
    public function index(): Response
    {
        $cours = auth()->user()
            ->coursInscrits()
            ->with('enseignant:id,prenom,nom')
            ->orderBy('nom_cours')
            ->get();

        return Inertia::render('Cours/Index', [
            'cours' => $cours,
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
