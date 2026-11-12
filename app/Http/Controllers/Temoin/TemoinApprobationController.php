<?php

namespace App\Http\Controllers\Temoin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Fiche et approbation des témoins (personnes âgées) par un enseignant
 * lié par ses thématiques, ou par un admin.
 */
class TemoinApprobationController extends Controller
{
    /**
     * Affiche la fiche détaillée d'un témoin lié aux thématiques de l'enseignant connecté.
     *
     * @throws AuthorizationException si le témoin n'est pas une personne âgée ou n'est pas lié à cet enseignant
     */
    public function show(User $user): Response
    {
        $this->authorize('voirTemoin', $user);

        $user->load(['thematiquesChoisies:id,nom', 'etablissementsChoisis']);

        return Inertia::render('Enseignant/TemoinShow', [
            'temoin' => [
                'id' => $user->id,
                'prenom' => $user->prenom,
                'nom' => $user->nom,
                'email' => $user->email,
                'description' => $user->description,
                'provenance' => $user->provenance,
                'theme_libre' => $user->themeLibre(),
                'statut' => $user->statut,
                'created_at' => $user->created_at->toIso8601String(),
                'thematiques_choisies' => $user->thematiquesChoisies->map->only('id', 'nom')->values(),
                'engagements_acceptes_le' => $user->engagements_acceptes_le?->toIso8601String(),
                'signature_electronique' => $user->signature_electronique,
            ],
        ]);
    }

    /**
     * Approuve l'inscription d'un témoin lié aux thématiques de l'enseignant connecté (ou par un admin).
     *
     * Vérifie que le témoin est bien associé à une thématique de l'enseignant
     * avant d'activer le compte.
     *
     * @throws AuthorizationException si le témoin n'est pas lié à cet enseignant ou n'est pas en attente
     */
    public function approuver(User $user): RedirectResponse
    {
        $this->authorize('approuverTemoin', $user);

        $user->statut = 'actif';
        $user->approuve_par_id = auth()->id();
        $user->email_verified_at = now();
        $user->save();

        return back()->with('success', __('temoin_approbation.approuve', ['nom' => $user->prenom.' '.$user->nom]));
    }

    /**
     * Refuse l'inscription d'un témoin lié aux thématiques de l'enseignant connecté.
     *
     * @throws AuthorizationException si le témoin n'est pas lié à cet enseignant ou n'est pas en attente
     */
    public function decliner(User $user): RedirectResponse
    {
        $this->authorize('declinerTemoin', $user);

        $user->statut = 'refuse';
        $user->save();

        return back()->with('success', __('temoin_approbation.decline', ['nom' => $user->prenom.' '.$user->nom]));
    }

    /**
     * Révoque l'approbation d'un témoin actif lié aux thématiques de l'enseignant connecté.
     *
     * Remet le statut à « en_attente » et retire la vérification de l'email.
     *
     * @throws AuthorizationException si le témoin n'est pas actif ou n'a pas été approuvé par l'utilisateur
     */
    public function desapprouver(User $user): RedirectResponse
    {
        $this->authorize('desapprouverTemoin', $user);

        $user->statut = 'en_attente';
        $user->approuve_par_id = null;
        $user->email_verified_at = null;
        $user->save();

        return back()->with('success', __('temoin_approbation.remis_en_attente', ['nom' => $user->prenom.' '.$user->nom]));
    }
}
