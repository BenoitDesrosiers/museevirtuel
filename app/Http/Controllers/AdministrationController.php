<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEnseignantRequest;
use App\Http\Requests\UpdateEnseignantRequest;
use App\Models\Cours;
use App\Models\Etablissement;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AdministrationController extends Controller
{
    /**
     * Affiche le tableau de bord d'administration avec la liste des enseignants et les statistiques.
     */
    public function index(): Response
    {
        $enseignants = User::where('role', 'enseignant')
            ->withCount(['cours', 'thematiques'])
            ->with('etablissement:id,nom')
            ->orderBy('nom')
            ->get();

        $stats = [
            'total_enseignants' => User::where('role', 'enseignant')->count(),
            'total_classes' => Cours::count(),
            'total_etudiants' => User::where('role', 'etudiant')->count(),
        ];

        $temoinsEnAttente = User::enAttente()
            ->where('role', 'personne_agee')
            ->with('thematique:id,nom')
            ->orderBy('created_at')
            ->get(['id', 'prenom', 'nom', 'email', 'description', 'thematique_id', 'theme_libre', 'created_at']);

        $etablissements = Etablissement::withCount(['enseignants', 'thematiques'])
            ->orderBy('nom')
            ->get();

        return Inertia::render('Administration/Index', [
            'enseignants' => $enseignants,
            'stats' => $stats,
            'temoinsEnAttente' => $temoinsEnAttente,
            'etablissements' => $etablissements,
        ]);
    }

    /**
     * Crée un nouveau compte enseignant.
     *
     * Le mot de passe initial est hashé explicitement (même si le cast 'hashed' le ferait).
     * L'email est validé pour unicité via StoreEnseignantRequest.
     */
    public function storeEnseignant(StoreEnseignantRequest $request): RedirectResponse
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
    public function updateEnseignant(UpdateEnseignantRequest $request, User $enseignant): RedirectResponse
    {
        // Protège contre la modification accidentelle d'un admin ou étudiant via route binding
        abort_if($enseignant->role !== 'enseignant', 403);

        $enseignant->update($request->validated());

        return back()->with('success', __('enseignant.updated'));
    }

    /**
     * Supprime un compte enseignant.
     *
     * @throws HttpException si la cible n'est pas un enseignant
     */
    public function destroyEnseignant(User $enseignant): RedirectResponse
    {
        // Protège contre la suppression accidentelle d'un admin ou étudiant via route binding
        abort_if($enseignant->role !== 'enseignant', 403);

        $enseignant->delete();

        return back()->with('success', __('enseignant.deleted'));
    }

    /**
     * Approuve l'inscription d'un témoin (personne âgée) en attente.
     *
     * Active le compte en passant son statut à 'actif' et marque l'email comme vérifié.
     *
     * @throws HttpException si la cible n'est pas une personne âgée en attente
     */
    public function approuverTemoin(User $user): RedirectResponse
    {
        abort_if($user->role !== 'personne_agee' || ! $user->estEnAttente(), 403);

        // email_verified_at n'est pas dans $fillable — on l'assigne directement
        $user->statut = 'actif';
        $user->email_verified_at = now();
        $user->save();

        return back()->with('success', __('Le compte de '.$user->prenom.' '.$user->nom.' a été approuvé.'));
    }

    /**
     * Refuse l'inscription d'un témoin (personne âgée) en attente.
     *
     * @throws HttpException si la cible n'est pas une personne âgée en attente
     */
    public function declinerTemoin(User $user): RedirectResponse
    {
        abort_if($user->role !== 'personne_agee' || ! $user->estEnAttente(), 403);

        $user->statut = 'refuse';
        $user->save();

        return back()->with('success', __('La demande de '.$user->prenom.' '.$user->nom.' a été déclinée.'));
    }
}
