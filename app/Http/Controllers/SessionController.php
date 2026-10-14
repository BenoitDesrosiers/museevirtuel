<?php

namespace App\Http\Controllers;

use App\Models\Classe;
use App\Models\Cours;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Fonctionnalité à supprimer. Elle sert de test pour pouvoir passer du role
 * Enseignant à Etudiant, vice versa
 */
class SessionController extends Controller
{
    /**
     * Change le rôle de l'utilisateur connecté pour les besoins de test.
     */
    public function changerRole(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'string', 'in:etudiant,enseignant'],
        ]);

        $user = $request->user();
        $coursEnseignes = $user->isEnseignant()
            ? $user->cours()->with('classes:id,cours_id')->get()
            : collect();

        DB::transaction(function () use ($user, $validated, $coursEnseignes): void {
            $ancienRole = $user->role;
            $nouveauRole = $validated['role'];

            $user->role = $nouveauRole;
            $user->save();

            if ($ancienRole === 'enseignant' && $nouveauRole === 'etudiant') {
                $this->inscrireCommeEtudiantDansSesCours($user, $coursEnseignes);
            }
        });

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('login');
    }

    /**
     * Inscrit un ancien enseignant dans toutes les classes de ses cours.
     */
    private function inscrireCommeEtudiantDansSesCours(User $user, Collection $coursEnseignes): void
    {
        $coursEnseignes->each(function (Cours $cours) use ($user): void {
            $cours->classes->each(function (Classe $classe) use ($user): void {
                $classe->etudiants()->syncWithoutDetaching([
                    $user->id => [
                        'no_da' => $user->no_da,
                        'statut_cours' => 'actif',
                    ],
                ]);
            });
        });
    }
}
