<?php

namespace App\Http\Controllers;

use App\Http\Requests\Etudiant\StoreEtudiantReferenceRequest;
use App\Models\EtudiantReference;
use App\Services\OrdreElements;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;

/**
 * Liste de références bibliographiques personnelle d'un étudiant.
 */
class EtudiantReferenceController extends Controller
{
    /**
     * Ajoute manuellement une référence bibliographique à la liste personnelle de l'étudiant.
     *
     * L'ordre est calculé automatiquement (max existant + 1).
     * Les données sont validées par StoreEtudiantReferenceRequest.
     */
    public function store(StoreEtudiantReferenceRequest $request, OrdreElements $ordres): RedirectResponse
    {
        $validated = $request->validated();

        $user = auth()->user();

        $ordre = $ordres->prochain($user->etudiantReferences());

        $user->etudiantReferences()->create([
            'titre' => $validated['titre'],
            'url' => $validated['url'] ?? null,
            'ordre' => $ordre,
        ]);

        return back()->with('success', __('etudiant_reference.created'));
    }

    /**
     * Supprime une référence personnelle de l'étudiant authentifié.
     *
     * Vérifie que la référence appartient bien à l'étudiant avant de supprimer.
     *
     * @throws AuthorizationException
     */
    public function destroy(EtudiantReference $reference): RedirectResponse
    {
        $this->authorize('delete', $reference);

        $reference->delete();

        return back()->with('success', __('etudiant_reference.deleted'));
    }
}
