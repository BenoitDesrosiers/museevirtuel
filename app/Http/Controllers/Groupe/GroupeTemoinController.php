<?php

namespace App\Http\Controllers\Groupe;

use App\Concerns\ValidatesGroupeChain;
use App\Http\Controllers\Controller;
use App\Http\Requests\Groupe\StoreGroupeTemoinRequest;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;

/**
 * Controleur de gestion des assignations de témoin (personne âgée) d'un groupe.
 */
class GroupeTemoinController extends Controller
{
    use ValidatesGroupeChain;

    /**
     * Assigne ou désassigne un témoin (personne âgée) au groupe.
     *
     * Données validées par StoreGroupeTemoinRequest.
     *
     * @throws AuthorizationException
     */
    public function __invoke(StoreGroupeTemoinRequest $request, Cours $cours, Classe $classe, Groupe $groupe): RedirectResponse
    {
        $this->verifierChaine($cours, $classe, $groupe);
        $groupe->load('classe.cours');
        $this->authorize('assignerTemoin', $groupe);

        $validated = $request->validated();

        $groupe->update(['personne_agee_id' => $validated['personne_agee_id']]);

        return back()->with('success', __('groupe.temoin_updated'));
    }
}
