<?php

namespace App\Http\Controllers\TypeProjet;

use App\Exceptions\TransactionEchoueeException;
use App\Http\Controllers\Controller;
use App\Http\Requests\TypeProjet\ReorderTypeProjetTacheRequest;
use App\Http\Requests\TypeProjet\SaveTypeProjetTacheRequest;
use App\Models\Cours;
use App\Models\TypeProjet;
use App\Models\TypeProjetTache;
use App\Services\OrdreElements;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;

class TypeProjetTacheController extends Controller
{
    /**
     * Ajoute une tâche dans un TypeProjet de type 'tache'.
     *
     * Accessible aux enseignants et admins uniquement (authorize sur le cours).
     * Les données sont validées par SaveTypeProjetTacheRequest.
     */
    public function store(
        SaveTypeProjetTacheRequest $request,
        Cours $cours,
        TypeProjet $typeProjet,
        OrdreElements $ordres,
    ): RedirectResponse {
        $data = $request->validated();

        $ordre = $ordres->prochain($typeProjet->taches());

        $typeProjet->taches()->create([
            'titre' => $data['titre'],
            'description' => $data['description'] ?? null,
            'ordre' => $ordre,
        ]);

        return back()->with('success', __('taches.added'));
    }

    /**
     * Met à jour le titre ou la description d'une tâche.
     *
     * Les données sont validées par SaveTypeProjetTacheRequest.
     */
    public function update(
        SaveTypeProjetTacheRequest $request,
        Cours $cours,
        TypeProjet $typeProjet,
        TypeProjetTache $tache,
    ): RedirectResponse {
        $data = $request->validated();

        $tache->update($data);

        return back()->with('success', __('taches.updated'));
    }

    /**
     * Réordonne les tâches d'un TypeProjet.
     *
     * Reçoit un tableau d'IDs dans l'ordre désiré.
     */
    public function reorder(
        ReorderTypeProjetTacheRequest $request,
        Cours $cours,
        TypeProjet $typeProjet,
        OrdreElements $ordres,
    ): RedirectResponse {
        try {
            $ordres->reordonner($typeProjet->taches(), $request->ids());
        } catch (TransactionEchoueeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back();
    }

    /**
     * Supprime une tâche et renumérote les tâches restantes.
     *
     * @throws AuthorizationException
     */
    public function destroy(
        Cours $cours,
        TypeProjet $typeProjet,
        TypeProjetTache $tache,
        OrdreElements $ordres,
    ): RedirectResponse {
        abort_if($typeProjet->cours_id !== $cours->id, 404);
        $this->authorize('update', $typeProjet);
        abort_if($tache->type_projet_id !== $typeProjet->id, 404);

        try {
            $ordres->supprimer($tache, $typeProjet->taches());
        } catch (TransactionEchoueeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('taches.deleted'));
    }
}
