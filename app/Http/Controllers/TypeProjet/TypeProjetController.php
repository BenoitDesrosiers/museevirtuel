<?php

namespace App\Http\Controllers\TypeProjet;

use App\Http\Controllers\Controller;
use App\Http\Requests\TypeProjet\StoreTypeProjetRequest;
use App\Http\Requests\TypeProjet\UpdateTypeProjetRequest;
use App\Models\Cours;
use App\Models\TypeProjet;
use App\Services\TypeProjetService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Types de projet d'un cours : liste, création, édition, accessibilité et suppression.
 */
class TypeProjetController extends Controller
{
    /**
     * Affiche la liste des types de projet d'un cours.
     *
     * @throws AuthorizationException
     */
    public function index(Cours $cours): Response
    {
        $this->authorize('update', $cours);

        $typesProjets = TypeProjet::where('cours_id', $cours->id)
            ->with(['sections'])
            ->orderBy('nom')
            ->get();

        return Inertia::render('TypeProjet/Index', [
            'cours' => $cours,
            'typesProjets' => $typesProjets,
        ]);
    }

    /**
     * Affiche la page de création d'un type de projet pour un cours donné.
     *
     * @throws AuthorizationException
     */
    public function create(Cours $cours): Response
    {
        $this->authorize('update', $cours);

        return Inertia::render('TypeProjet/Create', [
            'cours' => $cours,
        ]);
    }

    /**
     * Affiche la page d'édition dédiée d'un type de projet.
     *
     * Charge les sections avec leurs questions et leurs critères, ainsi que
     * les critères globaux (sans section) pour les afficher en tête de page.
     *
     * @throws HttpException si le type de projet n'appartient pas au cours
     * @throws AuthorizationException
     */
    public function edit(Cours $cours, TypeProjet $typeProjet): Response
    {
        abort_if($typeProjet->cours_id !== $cours->id, 404);
        $this->authorize('update', $typeProjet);

        $typeProjet->load([
            'sections' => fn ($q) => $q->with(['questionsBanque', 'criteres'])->orderBy('ordre'),
            'criteresGlobaux',
        ]);

        return Inertia::render('TypeProjet/Edit', [
            'cours' => $cours,
            'typeProjet' => $typeProjet,
            'criteresGlobaux' => $typeProjet->criteresGlobaux,
        ]);
    }

    /**
     * Crée un nouveau type de projet pour le cours donné.
     *
     * Accepte un tableau optionnel `sections[]` pour créer les sections en une seule requête.
     * Les données sont validées par StoreTypeProjetRequest ; la création est faite par TypeProjetService.
     */
    public function store(StoreTypeProjetRequest $request, Cours $cours, TypeProjetService $typeProjetService): RedirectResponse
    {
        $typeProjet = $typeProjetService->creer($request->user(), $cours, $request->validated());

        if ($typeProjet->isMusee()) {
            return redirect()->route('types-projets.musee-template.edit', [$cours, $typeProjet])
                ->with('success', __('type_projet.musee_created'));
        }

        return redirect()->route('types-projets.edit', [$cours, $typeProjet])
            ->with('success', __('type_projet.created'));
    }

    /**
     * Met à jour le nom, la description et synchronise les sections d'un type de projet.
     *
     * Quand `sections[]` est présent, effectue un sync complet :
     * les sections absentes sont supprimées (cascade sur projet_section_contenus),
     * les existantes (avec `id`) sont mises à jour, les nouvelles (sans `id`) sont créées.
     * Si `sections` est absent de la requête, les sections ne sont pas touchées.
     * Les données sont validées par UpdateTypeProjetRequest ; la mise à jour est faite par TypeProjetService.
     */
    public function update(UpdateTypeProjetRequest $request, Cours $cours, TypeProjet $typeProjet, TypeProjetService $typeProjetService): RedirectResponse
    {
        $typeProjetService->mettreAJour($typeProjet, $request->validated());

        return redirect()->route('types-projets.index', $cours)
            ->with('success', __('type_projet.updated'));
    }

    /**
     * Inverse le statut d'accessibilité du type de projet.
     *
     * Si `accessible = false`, les étudiants ne voient pas les projets associés.
     *
     * @throws HttpException si le type de projet n'appartient pas au cours
     * @throws AuthorizationException
     */
    public function toggleAccessible(Cours $cours, TypeProjet $typeProjet): RedirectResponse
    {
        abort_if($typeProjet->cours_id !== $cours->id, 404);
        $this->authorize('update', $typeProjet);

        $typeProjet->update(['accessible' => ! $typeProjet->accessible]);

        return back();
    }

    /**
     * Supprime un type de projet ainsi que sa grille en cascade.
     *
     * @throws HttpException si le type de projet n'appartient pas au cours
     * @throws AuthorizationException
     */
    public function destroy(Cours $cours, TypeProjet $typeProjet): RedirectResponse
    {
        abort_if($typeProjet->cours_id !== $cours->id, 404);
        $this->authorize('delete', $typeProjet);

        $typeProjet->delete();

        return redirect()->route('types-projets.index', $cours)
            ->with('success', __('type_projet.deleted'));
    }
}
