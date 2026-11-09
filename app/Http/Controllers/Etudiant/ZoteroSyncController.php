<?php

namespace App\Http\Controllers\Etudiant;

use App\Exceptions\TransactionEchoueeException;
use App\Http\Controllers\Controller;
use App\Services\ZoteroService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;

/**
 * Synchronisation de la bibliothèque Zotero de l'étudiant.
 */
class ZoteroSyncController extends Controller
{
    /**
     * Synchronise la bibliothèque Zotero de l'étudiant avec ses références en base.
     *
     * Utilise un upsert sur (user_id, zotero_item_key) pour éviter les doublons.
     * Si les credentials sont absents ou invalides, redirige avec un message d'erreur.
     *
     * @throws ConnectionException
     */
    public function __invoke(ZoteroService $zoteroService): RedirectResponse
    {
        $user = auth()->user();
        $credential = $user->zoteroCredential;

        if (! $credential) {
            return back()->withErrors(['zotero' => __('zotero.non_configure')]);
        }

        try {
            $items = $zoteroService->fetchItems($credential->zotero_user_id, $credential->api_key);
        } catch (ConnectionException) {
            return back()->withErrors(['zotero' => __('zotero.connexion_impossible')]);
        } catch (RequestException $e) {
            if ($e->response->status() === 403) {
                return back()->withErrors(['zotero' => __('zotero.cle_revoquee')]);
            }

            return back()->withErrors(['zotero' => __('zotero.erreur_sync')]);
        }

        try {
            $zoteroService->importerReferences($user, $credential, $items);
        } catch (TransactionEchoueeException $e) {
            return back()->withErrors(['zotero' => $e->getMessage()]);
        }

        return back()->with('success', __('zotero.synchronise', ['count' => count($items)]));
    }
}
