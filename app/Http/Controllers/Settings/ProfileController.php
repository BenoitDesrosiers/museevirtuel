<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\Groupe;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'temoinAssocieAGroupe' => $user->role === 'personne_agee'
                && Groupe::where('personne_agee_id', $user->id)->exists(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     *
     * Admins et personnes âgées peuvent supprimer leur propre compte.
     * Étudiants et enseignants ne peuvent pas s'auto-supprimer.
     * Un témoin associé à au moins un groupe ne peut pas s'auto-supprimer.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $role = $request->user()->role;
        abort_if($role !== 'admin' && $role !== 'personne_agee', 403);

        $user = $request->user();

        // Un témoin assigné à un groupe ne peut pas supprimer son compte.
        if ($role === 'personne_agee' && Groupe::where('personne_agee_id', $user->id)->exists()) {
            abort(403, 'Votre compte est lié à un groupe actif. Contactez un administrateur.');
        }

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
