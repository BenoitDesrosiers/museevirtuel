<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveEnseignantRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;

/**
 * Gestion des comptes enseignants par l'administration.
 */
class EnseignantController extends Controller
{
    /**
     * Crée un nouveau compte enseignant.
     *
     * Le mot de passe initial est hashé explicitement (même si le cast 'hashed' le ferait).
     * L'email est validé pour unicité via SaveEnseignantRequest.
     */
    public function store(SaveEnseignantRequest $request): RedirectResponse
    {
        User::create([
            ...$request->validated(),
            'password' => Hash::make('Enseignant'),
            'role' => 'enseignant',
            'email_verified_at' => now(),
        ]);

        return back()->with('success', __('enseignant.created'));
    }

    /**
     * Met à jour les informations d'un enseignant.
     *
     * @throws AuthorizationException si la cible n'est pas un enseignant
     */
    public function update(SaveEnseignantRequest $request, User $enseignant): RedirectResponse
    {
        $enseignant->update($request->validated());

        return back()->with('success', __('enseignant.updated'));
    }

    /**
     * Supprime un compte enseignant.
     *
     * @throws AuthorizationException si la cible n'est pas un enseignant
     */
    public function destroy(User $enseignant): RedirectResponse
    {
        $this->authorize('gererEnseignant', $enseignant);

        $enseignant->delete();

        return back()->with('success', __('enseignant.deleted'));
    }
}
