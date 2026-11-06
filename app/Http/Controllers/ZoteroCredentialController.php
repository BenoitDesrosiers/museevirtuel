<?php

namespace App\Http\Controllers;

use App\Exceptions\TransactionEchoueeException;
use App\Http\Requests\Zotero\StoreZoteroCredentialRequest;
use App\Services\ZoteroService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;

/**
 * Identifiants Zotero de l'étudiant (clé API + identifiant utilisateur).
 */
class ZoteroCredentialController extends Controller
{
    /**
     * Sauvegarde les credentials Zotero de l'étudiant (clé API + identifiant utilisateur).
     *
     * Le format est validé par StoreZoteroCredentialRequest, puis la clé API
     * est validée contre l'API Zotero avant d'être chiffrée et stockée.
     *
     * @throws ConnectionException
     */
    public function store(
        StoreZoteroCredentialRequest $request,
        ZoteroService $zoteroService): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $valide = $zoteroService->validateApiKey($validated['zotero_user_id'], $validated['api_key']);
        } catch (ConnectionException) {
            return back()->withErrors(['api_key' => __('zotero.connexion_impossible')]);
        }

        if (! $valide) {
            return back()->withErrors(['api_key' => __('zotero.identifiants_invalides')]);
        }

        // La clé est chiffrée automatiquement par le cast 'encrypted' du modèle
        auth()->user()->zoteroCredential()->updateOrCreate(
            ['user_id' => auth()->id()],
            [
                'zotero_user_id' => $validated['zotero_user_id'],
                'api_key' => $validated['api_key'],
            ],
        );

        return back()->with('success', __('zotero.configure'));
    }

    /**
     * Supprime les credentials Zotero et toutes les références importées de Zotero.
     *
     * Les références ajoutées manuellement (zotero_item_key = null) sont conservées.
     */
    public function destroy(ZoteroService $zoteroService): RedirectResponse
    {
        try {
            $zoteroService->deconnecter(auth()->user());
        } catch (TransactionEchoueeException $e) {
            return back()->withErrors(['zotero' => $e->getMessage()]);
        }

        return back()->with('success', __('zotero.deconnecte'));
    }
}
