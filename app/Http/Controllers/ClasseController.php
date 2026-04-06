<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClasseRequest;
use App\Http\Requests\UpdateClasseRequest;
use App\Models\Classe;
use App\Models\TypeProjet;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ClasseController extends Controller
{
    /**
     * Affiche la liste des classes dans lesquelles l'étudiant authentifié est inscrit.
     */
    public function index(): Response
    {
        $classes = auth()->user()
            ->classesInscrites()
            ->with('enseignant:id,prenom,nom')
            ->get();

        return Inertia::render('Classes/Index', [
            'classes' => $classes,
        ]);
    }

    /**
     * Affiche le détail d'une classe avec ses étudiants, groupes et documents.
     *
     * Accessible aux enseignants propriétaires et aux admins (ClassePolicy::view).
     */
    public function show(Classe $classe): Response
    {
        $this->authorize('view', $classe);

        $etudiants = $classe->etudiants()
            ->orderBy('nom')
            ->get()
            ->map(fn ($etudiant) => [
                'id' => $etudiant->id,
                'prenom' => $etudiant->prenom,
                'nom' => $etudiant->nom,
                'email' => $etudiant->email,
                'no_da' => $etudiant->no_da,
                'statut_cours' => $etudiant->pivot->statut_cours,
            ]);

        $groupes = $classe->groupes()
            ->with(['membres:id,prenom,nom', 'thematiques:id,nom', 'createur:id,prenom,nom'])
            ->get();

        $documents = $classe->documents()->get();

        $echeancierEtapes = $classe->echeancierEtapes()
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

        return Inertia::render('Classes/Show', [
            'classe' => $classe,
            'etudiants' => $etudiants,
            'groupes' => $groupes,
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
     * Enregistre une nouvelle classe pour l'enseignant authentifié.
     *
     * La validation et l'autorisation sont déléguées à StoreClasseRequest.
     */
    public function store(StoreClasseRequest $request): RedirectResponse
    {
        auth()->user()->classes()->create($request->validated());

        return back()->with('success', __('classe.created'));
    }

    /**
     * Met à jour une classe existante.
     *
     * La validation et l'autorisation (ClassePolicy::update) sont déléguées à UpdateClasseRequest.
     */
    public function update(UpdateClasseRequest $request, Classe $classe): RedirectResponse
    {
        $classe->update($request->validated());

        return back()->with('success', __('classe.updated'));
    }

    /**
     * Supprime une classe et toutes ses données associées.
     *
     * @throws AuthorizationException
     */
    public function destroy(Classe $classe): RedirectResponse
    {
        $this->authorize('delete', $classe);

        $classe->delete();

        return to_route('enseignant.index')->with('success', __('classe.deleted'));
    }
}
