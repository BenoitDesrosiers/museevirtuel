<?php

namespace App\Http\Controllers\Groupe;

use App\Concerns\ValidatesGroupeChain;
use App\Exceptions\TransactionEchoueeException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Groupe\UpdateGroupeThematiqueRequest;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Services\GroupeService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;

/**
 * Thématiques d'un groupe.
 */
class GroupeThematiqueController extends Controller
{
    use ValidatesGroupeChain;

    /**
     * Remplace complètement les thématiques du groupe (sync).
     *
     * Données validées par UpdateGroupeThematiqueRequest.
     *
     * @throws AuthorizationException
     * @throws ValidationException si la composition du groupe est invalide
     */
    public function update(UpdateGroupeThematiqueRequest $request, Cours $cours, Classe $classe, Groupe $groupe, GroupeService $groupeService): RedirectResponse
    {
        $this->verifierChaine($cours, $classe, $groupe);
        $groupe->load('classe.cours');
        $this->authorize('manageThematiques', $groupe);

        try {
            $groupeService->synchroniserThematiques($cours, $groupe, $request->validated('thematiques') ?? []);
        } catch (TransactionEchoueeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('groupe.thematiques_updated'));
    }
}
