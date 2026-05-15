<?php

namespace App\Http\Controllers;

use App\Models\EtudiantReference;
use App\Services\ZoteroService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EtudiantReferenceController extends Controller
{
    /**
     * Ajoute manuellement une référence bibliographique à la liste personnelle de l'étudiant.
     *
     * L'ordre est calculé automatiquement (max existant + 1).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'titre' => ['required', 'string', 'max:500'],
            'url' => ['nullable', 'url', 'max:500'],
        ]);

        $user = auth()->user();

        $ordre = $user->etudiantReferences()->max('ordre') + 1;

        $user->etudiantReferences()->create([
            'titre' => $validated['titre'],
            'url' => $validated['url'] ?? null,
            'ordre' => $ordre,
        ]);

        return back()->with('success', 'Référence ajoutée.');
    }

    /**
     * Supprime une référence personnelle de l'étudiant authentifié.
     *
     * Vérifie que la référence appartient bien à l'étudiant avant de supprimer.
     */
    public function destroy(EtudiantReference $reference): RedirectResponse
    {
        abort_if($reference->user_id !== auth()->id(), 403);

        $reference->delete();

        return back()->with('success', 'Référence supprimée.');
    }

    /**
     * Synchronise la bibliothèque Zotero de l'étudiant avec ses références en base.
     *
     * Utilise un upsert sur (user_id, zotero_item_key) pour éviter les doublons.
     * Si les credentials sont absents ou invalides, redirige avec un message d'erreur.
     *
     * @throws ConnectionException
     */
    public function syncZotero(ZoteroService $zotero): RedirectResponse
    {
        $user = auth()->user();
        $credential = $user->zoteroCredential;

        if (! $credential) {
            return back()->withErrors(['zotero' => 'Configurez votre compte Zotero avant de synchroniser.']);
        }

        try {
            $items = $zotero->fetchItems($credential->zotero_user_id, $credential->api_key);
        } catch (ConnectionException) {
            return back()->withErrors(['zotero' => 'Impossible de joindre l\'API Zotero. Réessayez dans quelques instants.']);
        } catch (RequestException $e) {
            // 403 = clé révoquée ou invalide
            if ($e->response->status() === 403) {
                return back()->withErrors(['zotero' => 'La clé API Zotero est invalide ou a été révoquée. Reconfigurez votre compte.']);
            }

            return back()->withErrors(['zotero' => 'Erreur lors de la synchronisation Zotero.']);
        }

        $maxOrdre = $user->etudiantReferences()->max('ordre') ?? 0;

        foreach ($items as $item) {
            if (! $item['zotero_item_key']) {
                continue;
            }

            $user->etudiantReferences()->updateOrCreate(
                ['zotero_item_key' => $item['zotero_item_key']],
                array_merge($item, ['ordre' => ++$maxOrdre]),
            );
        }

        $credential->update(['synchronise_le' => now()]);

        return back()->with('success', count($items).' référence(s) synchronisée(s) depuis Zotero.');
    }

    /**
     * Sauvegarde les credentials Zotero de l'étudiant (clé API + identifiant utilisateur).
     *
     * La clé API est validée contre l'API Zotero avant d'être chiffrée et stockée.
     *
     * @throws ConnectionException
     */
    public function saveCredential(Request $request, ZoteroService $zotero): RedirectResponse
    {
        $validated = $request->validate([
            'zotero_user_id' => ['required', 'string', 'max:50', 'regex:/^\d+$/'],
            'api_key' => ['required', 'string', 'max:255'],
        ]);

        try {
            $valide = $zotero->validateApiKey($validated['zotero_user_id'], $validated['api_key']);
        } catch (ConnectionException) {
            return back()->withErrors(['api_key' => 'Impossible de joindre l\'API Zotero. Réessayez dans quelques instants.']);
        }

        if (! $valide) {
            return back()->withErrors(['api_key' => 'Clé API ou identifiant Zotero invalide. Vérifiez vos informations.']);
        }

        // La clé est chiffrée automatiquement par le cast 'encrypted' du modèle
        auth()->user()->zoteroCredential()->updateOrCreate(
            ['user_id' => auth()->id()],
            [
                'zotero_user_id' => $validated['zotero_user_id'],
                'api_key' => $validated['api_key'],
            ],
        );

        return back()->with('success', 'Compte Zotero configuré avec succès.');
    }

    /**
     * Supprime les credentials Zotero et toutes les références importées de Zotero.
     *
     * Les références ajoutées manuellement (zotero_item_key = null) sont conservées.
     */
    public function destroyCredential(): RedirectResponse
    {
        $user = auth()->user();

        // Supprimer uniquement les références provenant de Zotero
        $user->etudiantReferences()->whereNotNull('zotero_item_key')->delete();

        $user->zoteroCredential()?->delete();

        return back()->with('success', 'Compte Zotero déconnecté et références Zotero supprimées.');
    }
}
