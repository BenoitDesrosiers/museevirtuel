<?php

namespace App\Http\Controllers\Cours;

use App\Exceptions\TransactionEchoueeException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cours\ReorderCoursObjectifRequest;
use App\Http\Requests\Cours\SaveCoursObjectifRequest;
use App\Models\Cours;
use App\Models\CoursObjectif;
use App\Services\OrdreElements;
use Illuminate\Http\RedirectResponse;

class CoursObjectifController extends Controller
{
    /**
     * Ajoute un objectif pédagogique à un cours.
     *
     * Réservé à l'enseignant du cours et aux admins.
     * Les données sont validées par SaveCoursObjectifRequest.
     */
    public function store(SaveCoursObjectifRequest $request, Cours $cours, OrdreElements $ordres): RedirectResponse
    {
        $validated = $request->validated();

        $ordre = $ordres->prochain($cours->objectifs());

        $cours->objectifs()->create([
            'contenu' => $validated['contenu'],
            'ordre' => $ordre,
        ]);

        return back()->with('success', __('objectif.created'));
    }

    /**
     * Met à jour le contenu d'un objectif pédagogique.
     *
     * Réservé à l'enseignant du cours et aux admins.
     * Les données sont validées par SaveCoursObjectifRequest.
     */
    public function update(SaveCoursObjectifRequest $request, Cours $cours, CoursObjectif $objectif): RedirectResponse
    {
        $validated = $request->validated();

        $objectif->update($validated);

        return back()->with('success', __('objectif.updated'));
    }

    /**
     * Supprime un objectif pédagogique et renumérotise les suivants.
     *
     * Réservé à l'enseignant du cours et aux admins.
     */
    public function destroy(Cours $cours, CoursObjectif $objectif, OrdreElements $ordres): RedirectResponse
    {
        $this->authorize('update', $cours);
        abort_if($objectif->cours_id !== $cours->id, 404);

        try {
            $ordres->supprimer($objectif, $cours->objectifs());
        } catch (TransactionEchoueeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('objectif.deleted'));
    }

    /**
     * Réordonne les objectifs d'un cours.
     *
     * Reçoit un tableau d'IDs dans l'ordre désiré.
     */
    public function reorder(ReorderCoursObjectifRequest $request, Cours $cours, OrdreElements $ordres): RedirectResponse
    {
        try {
            $ordres->reordonner($cours->objectifs(), $request->ids());
        } catch (TransactionEchoueeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back();
    }
}
