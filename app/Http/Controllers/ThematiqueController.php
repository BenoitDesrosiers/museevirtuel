<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreThematiqueRequest;
use App\Http\Requests\UpdateThematiqueRequest;
use App\Models\Thematique;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;

class ThematiqueController extends Controller
{
    /**
     * Enregistre une nouvelle thématique pour l'enseignant authentifié.
     */
    public function store(StoreThematiqueRequest $request): RedirectResponse
    {
        if (is_null(auth()->user()->etablissement_id)) {
            return back()->withErrors(['nom' => __('thematique.no_etablissement')])->withInput();
        }

        // firstOrCreate sur (nom, etablissement_id) pour éviter les doublons
        // quand plusieurs enseignants du même établissement créent la même thématique.
        Thematique::firstOrCreate(
            [
                'nom' => $request->validated('nom'),
                'etablissement_id' => auth()->user()->etablissement_id,
            ],
            array_merge(
                $request->validated(),
                [
                    'enseignant_id' => auth()->user()->id,
                    'etablissement_id' => auth()->user()->etablissement_id,
                ],
            ),
        );

        return back()->with('success', __('thematique.created'));
    }

    /**
     * Met à jour une thématique existante.
     *
     * L'autorisation (ThematiquePolicy::update) est déléguée à UpdateThematiqueRequest.
     */
    public function update(UpdateThematiqueRequest $request, Thematique $thematique): RedirectResponse
    {
        $thematique->update($request->validated());

        return back()->with('success', __('thematique.updated'));
    }

    /**
     * Supprime une thématique.
     *
     * @throws AuthorizationException
     */
    public function destroy(Thematique $thematique): RedirectResponse
    {
        $this->authorize('delete', $thematique);

        $thematique->delete();

        return back()->with('success', __('thematique.deleted'));
    }
}
