<?php

namespace App\Http\Controllers\Cours;

use App\Exceptions\TransactionEchoueeException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cours\ReorderCoursReferenceRequest;
use App\Http\Requests\Cours\SaveCoursReferenceRequest;
use App\Models\Cours;
use App\Models\CoursReference;
use App\Services\OrdreElements;
use Illuminate\Http\RedirectResponse;

class CoursReferenceController extends Controller
{
    /**
     * Ajoute une référence bibliographique à un cours.
     *
     * Réservé à l'enseignant du cours et aux admins.
     * Les données sont validées par SaveCoursReferenceRequest.
     */
    public function store(SaveCoursReferenceRequest $request, Cours $cours, OrdreElements $ordres): RedirectResponse
    {
        $validated = $request->validated();

        $ordre = $ordres->prochain($cours->references());

        $cours->references()->create([
            'nom' => $validated['nom'],
            'url' => $validated['url'] ?? null,
            'ordre' => $ordre,
        ]);

        return back()->with('success', __('cours_reference.created'));
    }

    /**
     * Met à jour une référence bibliographique.
     *
     * Réservé à l'enseignant du cours et aux admins.
     * Les données sont validées par SaveCoursReferenceRequest.
     */
    public function update(SaveCoursReferenceRequest $request, Cours $cours, CoursReference $reference): RedirectResponse
    {
        $validated = $request->validated();

        $reference->update($validated);

        return back()->with('success', __('cours_reference.updated'));
    }

    /**
     * Supprime une référence bibliographique et renumérotise les suivantes.
     *
     * Réservé à l'enseignant du cours et aux admins.
     */
    public function destroy(Cours $cours, CoursReference $reference, OrdreElements $ordres): RedirectResponse
    {
        $this->authorize('update', $cours);
        abort_if($reference->cours_id !== $cours->id, 404);

        try {
            $ordres->supprimer($reference, $cours->references());
        } catch (TransactionEchoueeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('cours_reference.deleted'));
    }

    /**
     * Réordonne les références bibliographiques d'un cours.
     *
     * Reçoit un tableau d'IDs dans l'ordre désiré.
     */
    public function reorder(ReorderCoursReferenceRequest $request, Cours $cours, OrdreElements $ordres): RedirectResponse
    {
        try {
            $ordres->reordonner($cours->references(), $request->ids());
        } catch (TransactionEchoueeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back();
    }
}
