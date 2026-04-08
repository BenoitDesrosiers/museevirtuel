<?php

namespace App\Http\Controllers;

use App\Models\Thematique;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class InscriptionTemoinController extends Controller
{
    /**
     * Affiche le formulaire d'inscription public pour les témoins (personnes âgées).
     */
    public function show(): Response
    {
        return Inertia::render('auth/InscriptionTemoin', [
            'thematiques' => Thematique::orderBy('nom')->get(['id', 'nom']),
        ]);
    }

    /**
     * Enregistre une demande d'inscription de témoin en attente d'approbation.
     *
     * Le compte est créé avec statut 'en_attente' — il sera activé par un admin.
     * L'utilisateur doit choisir soit une thématique existante, soit saisir un thème libre.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'prenom' => ['required', 'string', 'max:255'],
            'nom' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            'thematique_id' => ['nullable', 'integer', 'exists:thematiques,id'],
            'theme_libre' => ['nullable', 'string', 'max:500'],
            'description' => ['required', 'string', 'max:2000'],
        ]);

        // Au moins un des deux champs de thème est requis
        if (empty($validated['thematique_id']) && empty($validated['theme_libre'])) {
            return back()->withErrors([
                'theme_libre' => __('Veuillez choisir une thématique ou saisir un thème libre.'),
            ])->withInput();
        }

        User::create([
            'prenom' => $validated['prenom'],
            'nom' => $validated['nom'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => 'personne_agee',
            'statut' => 'en_attente',
            'thematique_id' => $validated['thematique_id'] ?? null,
            'theme_libre' => $validated['theme_libre'] ?? null,
            'description' => $validated['description'],
        ]);

        return redirect()->route('inscription.temoin')
            ->with('success', __('Votre demande a été envoyée. Un administrateur examinera votre inscription prochainement.'));
    }
}
