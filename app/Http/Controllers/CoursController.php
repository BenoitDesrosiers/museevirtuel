<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCoursRequest;
use App\Http\Requests\UpdateCoursRequest;
use App\Models\Cours;
use App\Models\TypeProjet;
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
                'etape' => $etape->etape,
                'is_done' => $etape->is_done,
                'ordre' => $etape->ordre,
            ]);

        $typesProjets = TypeProjet::where('enseignant_id', auth()->id())
            ->with(['grille:id,type_projet_id,nom', 'sections'])
            ->orderBy('nom')
            ->get();

        return Inertia::render('Cours/Show', [
            'cours' => $cours,
            'classes' => $classes,
            'documents' => $documents,
            'echeancierEtapes' => $echeancierEtapes,
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
