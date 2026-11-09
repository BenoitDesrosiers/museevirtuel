<?php

namespace App\Http\Controllers\Groupe;

use App\Concerns\ValidatesGroupeChain;
use App\Exceptions\TransactionEchoueeException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Groupe\UpdateGroupeMembreRequest;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Services\GroupeService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;

/**
 * Contrôleur de gestion des membres d'un groupe.
 */
class GroupeMembreController extends Controller
{
    use ValidatesGroupeChain;

    /**
     * Ajoute ou retire des membres du groupe (créateur uniquement).
     *
     * Données validées par UpdateGroupeMembreRequest.
     *
     * @throws AuthorizationException
     * @throws ValidationException si la composition du groupe est invalide
     */
    public function update(UpdateGroupeMembreRequest $request, Cours $cours, Classe $classe,
        Groupe $groupe, GroupeService $groupeService): RedirectResponse
    {
        $this->verifierChaine($cours, $classe, $groupe);
        $groupe->load('classe.cours');
        $this->authorize('manageMembers', $groupe);

        $validated = $request->validated();

        try {
            $groupeService->modifierMembres(
                $request->user(),
                $cours,
                $classe,
                $groupe,
                $validated['ajouter'] ?? [],
                $validated['retirer'] ?? [],
            );
        } catch (TransactionEchoueeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('groupe.members_updated'));
    }
}
