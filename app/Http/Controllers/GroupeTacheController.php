<?php

namespace App\Http\Controllers;

use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\GroupeTache;
use App\Models\TypeProjet;
use App\Models\TypeProjetTache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class GroupeTacheController extends Controller
{
    /**
     * Assigne un membre du groupe à une tâche ou désassigne (assigne_a null).
     *
     * Seuls les membres du groupe peuvent modifier l'assignation.
     */
    public function assigner(
        Request $request,
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
        TypeProjetTache $tache,
    ): RedirectResponse {
        abort_if($classe->cours_id !== $cours->id, 404);
        abort_if($groupe->classe_id !== $classe->id, 404);
        abort_if($tache->type_projet_id !== $typeProjet->id, 404);

        abort_unless(
            $groupe->membres()->where('user_id', auth()->id())->exists(),
            403
        );

        $data = $request->validate([
            'assigne_a' => ['nullable', 'integer', 'exists:users,id'],
        ]);

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
     */
    public function toggleCompleted(
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
        TypeProjetTache $tache,
    ): RedirectResponse {
        abort_if($classe->cours_id !== $cours->id, 404);
        abort_if($groupe->classe_id !== $classe->id, 404);
        abort_if($tache->type_projet_id !== $typeProjet->id, 404);

        abort_unless(
            $groupe->membres()->where('user_id', auth()->id())->exists(),
            403
        );

        $groupeTache = GroupeTache::firstOrCreate(
            ['tache_id' => $tache->id, 'groupe_id' => $groupe->id],
        );

        $groupeTache->update([
            'completed_at' => $groupeTache->completed_at ? null : Carbon::now(),
        ]);

        return back();
    }
}
