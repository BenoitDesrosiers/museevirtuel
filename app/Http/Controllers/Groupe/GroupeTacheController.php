<?php

namespace App\Http\Controllers\Groupe;

use App\Concerns\ValidatesGroupeChain;
use App\Http\Controllers\Controller;
use App\Http\Requests\Groupe\AssignerGroupeTacheRequest;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\GroupeTache;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use App\Models\TypeProjetTache;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Suivi des tâches d'un projet par les membres du groupe : assignation et complétion.
 */
class GroupeTacheController extends Controller
{
    use ValidatesGroupeChain;

    /**
     * Assigne un membre du groupe à une tâche ou désassigne (assigne_a null).
     *
     * Seuls les membres du groupe peuvent modifier l'assignation.
     *
     * Données validées par AssignerGroupeTacheRequest.
     */
    public function assigner(
        AssignerGroupeTacheRequest $request,
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
        TypeProjetTache $tache,
    ): RedirectResponse {
        $data = $request->validated();

        // Vérifier que l'utilisateur assigné est bien membre du groupe
        if ($data['assigne_a'] !== null) {
            abort_unless(
                $groupe->membres()->where('user_id', $data['assigne_a'])->exists(),
                422
            );
        }

        GroupeTache::updateOrCreate(
            ['tache_id' => $tache->id, 'groupe_id' => $groupe->id],
            ['assigne_a' => $data['assigne_a']]
        );

        return back()->with('success', __('taches.assigned'));
    }

    /**
     * Bascule l'état complété/non-complété d'une tâche pour un groupe.
     *
     * Seuls les membres du groupe peuvent basculer.
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function toggleTerminee(
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
        TypeProjetTache $tache,
    ): RedirectResponse {
        $this->verifierChaineProjet($cours, $classe, $groupe, $typeProjet);
        abort_if($tache->type_projet_id !== $typeProjet->id, 404);

        $this->authorize('contribuer', [ProjetRecherche::class, $groupe]);

        $groupeTache = GroupeTache::firstOrCreate(
            ['tache_id' => $tache->id, 'groupe_id' => $groupe->id],
        );

        $groupeTache->update([
            'completed_at' => $groupeTache->completed_at ? null : Carbon::now(),
        ]);

        return back();
    }
}
