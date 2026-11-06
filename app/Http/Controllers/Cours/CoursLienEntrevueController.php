<?php

namespace App\Http\Controllers\Cours;

use App\Exceptions\TransactionEchoueeException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cours\ReorderCoursLienEntrevueRequest;
use App\Http\Requests\Cours\SaveCoursLienEntrevueRequest;
use App\Models\Cours;
use App\Models\CoursLienEntrevue;
use App\Services\OrdreElements;
use Illuminate\Http\RedirectResponse;

class CoursLienEntrevueController extends Controller
{
    /**
     * Ajoute un lien d'entrevue au cours.
     *
     * Accessible aux enseignants et admins (authorize sur le cours).
     * Les données sont validées par SaveCoursLienEntrevueRequest.
     */
    public function store(SaveCoursLienEntrevueRequest $request, Cours $cours, OrdreElements $ordres): RedirectResponse
    {
        $data = $request->validated();

        $ordre = $ordres->prochain($cours->liensEntrevue());

        $cours->liensEntrevue()->create([
            'label' => $data['label'],
            'url' => $data['url'],
            'ordre' => $ordre,
        ]);

        return back()->with('success', __('liens_entrevue.added'));
    }

    /**
     * Met à jour un lien d'entrevue existant.
     *
     * Les données sont validées par SaveCoursLienEntrevueRequest.
     */
    public function update(
        SaveCoursLienEntrevueRequest $request,
        Cours $cours,
        CoursLienEntrevue $lien,
    ): RedirectResponse {
        $data = $request->validated();

        $lien->update($data);

        return back()->with('success', __('liens_entrevue.updated'));
    }

    /**
     * Réordonne les liens d'entrevue du cours.
     *
     * Reçoit un tableau d'IDs dans l'ordre désiré.
     */
    public function reorder(ReorderCoursLienEntrevueRequest $request, Cours $cours, OrdreElements $ordres): RedirectResponse
    {
        try {
            $ordres->reordonner($cours->liensEntrevue(), $request->ids());
        } catch (TransactionEchoueeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back();
    }

    /**
     * Supprime un lien d'entrevue et renumérote les restants.
     */
    public function destroy(Cours $cours, CoursLienEntrevue $lien, OrdreElements $ordres): RedirectResponse
    {
        $this->authorize('update', $cours);
        abort_if($lien->cours_id !== $cours->id, 404);

        try {
            $ordres->supprimer($lien, $cours->liensEntrevue());
        } catch (TransactionEchoueeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('liens_entrevue.deleted'));
    }
}
