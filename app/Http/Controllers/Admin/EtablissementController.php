<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Etablissement\SaveEtablissementRequest;
use App\Models\Etablissement;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class EtablissementController extends Controller
{
    /**
     * Affiche le détail d'un établissement : enseignants et thématiques.
     */
    public function show(Etablissement $etablissement): Response
    {
        $etablissement->load([
            'enseignants' => fn ($q) => $q->withCount(['cours', 'thematiques'])->orderBy('nom'),
            'thematiques' => fn ($q) => $q->with('enseignant:id,prenom,nom')->orderBy('nom'),
        ]);

        return Inertia::render('Administration/Etablissement/Show', [
            'etablissement' => $etablissement,
        ]);
    }

    /**
     * Crée un nouvel établissement.
     *
     * Les données sont validées par SaveEtablissementRequest.
     */
    public function store(SaveEtablissementRequest $request): RedirectResponse
    {
        Etablissement::create($request->only('nom', 'ville', 'code'));

        return back()->with('success', __('etablissement.created'));
    }

    /**
     * Met à jour un établissement existant.
     *
     * Les données sont validées par SaveEtablissementRequest.
     */
    public function update(SaveEtablissementRequest $request, Etablissement $etablissement): RedirectResponse
    {
        $etablissement->update($request->only('nom', 'ville', 'code'));

        return back()->with('success', __('etablissement.updated'));
    }

    /**
     * Supprime un établissement.
     * Les enseignants et thématiques liés auront leur etablissement_id mis à NULL (SET NULL).
     */
    public function destroy(Etablissement $etablissement): RedirectResponse
    {
        $etablissement->delete();

        return back()->with('success', __('etablissement.deleted'));
    }
}
