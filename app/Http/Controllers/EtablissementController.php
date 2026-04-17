<?php

namespace App\Http\Controllers;

use App\Models\Etablissement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'ville' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'unique:etablissements,code'],
        ]);

        Etablissement::create($request->only('nom', 'ville', 'code'));

        return back()->with('success', __('Établissement créé avec succès.'));
    }

    /**
     * Met à jour un établissement existant.
     */
    public function update(Request $request, Etablissement $etablissement): RedirectResponse
    {
        $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'ville' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'unique:etablissements,code,'.$etablissement->id],
        ]);

        $etablissement->update($request->only('nom', 'ville', 'code'));

        return back()->with('success', __('Établissement mis à jour.'));
    }

    /**
     * Supprime un établissement.
     * Les enseignants et thématiques liés auront leur etablissement_id mis à NULL (SET NULL).
     */
    public function destroy(Etablissement $etablissement): RedirectResponse
    {
        $etablissement->delete();

        return back()->with('success', __('Établissement supprimé.'));
    }
}
