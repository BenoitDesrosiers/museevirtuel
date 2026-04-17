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
        auth()->user()->thematiques()->create(array_merge(
            $request->validated(),
            ['etablissement_id' => auth()->user()->etablissement_id],
        ));

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
