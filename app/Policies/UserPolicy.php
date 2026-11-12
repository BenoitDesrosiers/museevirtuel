<?php

namespace App\Policies;

use App\Models\Groupe;
use App\Models\Thematique;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Règles d'accès d'un utilisateur sur un autre utilisateur.
 *
 * Couvre la suppression de son propre compte, la gestion des comptes enseignants
 * par l'administration et la gestion des témoins (personnes âgées).
 */
class UserPolicy
{
    /**
     * Détermine si l'utilisateur peut supprimer ce compte (le sien uniquement).
     */
    public function delete(User $user, User $compte): Response
    {
        if ($user->id !== $compte->id || (! $user->isAdmin() && ! $user->isPersonneAgee())) {
            return Response::deny();
        }

        if ($user->isPersonneAgee() && Groupe::where('personne_agee_id', $user->id)->exists()) {
            return Response::deny(__('profile.suppression_temoin_lie_groupe'));
        }

        return Response::allow();
    }

    /**
     * Détermine si l'utilisateur peut modifier ou supprimer ce compte enseignant.
     *
     * Réservé aux admins, et seulement sur un compte enseignant : empêche de viser
     * un admin ou un étudiant en changeant l'identifiant dans l'URL.
     */
    public function gererEnseignant(User $user, User $enseignant): bool
    {
        return $user->isAdmin() && $enseignant->isEnseignant();
    }

    /**
     * Détermine si l'utilisateur peut consulter la fiche du témoin.
     */
    public function voirTemoin(User $user, User $temoin): bool
    {
        return $temoin->isPersonneAgee() && $this->estLieAuTemoin($user, $temoin);
    }

    /**
     * Détermine si l'utilisateur peut approuver l'inscription du témoin.
     *
     * Le témoin doit être en attente d'approbation.
     */
    public function approuverTemoin(User $user, User $temoin): bool
    {
        return $temoin->isPersonneAgee()
            && $temoin->estEnAttente()
            && $this->estLieAuTemoin($user, $temoin);
    }

    /**
     * Détermine si l'utilisateur peut refuser l'inscription du témoin.
     *
     * Mêmes règles que l'approbation : c'est l'autre issue de la même décision.
     */
    public function declinerTemoin(User $user, User $temoin): bool
    {
        return $this->approuverTemoin($user, $temoin);
    }

    /**
     * Détermine si l'utilisateur peut révoquer l'approbation du témoin.
     *
     * Seul celui qui a approuvé le témoin peut revenir sur sa décision.
     */
    public function desapprouverTemoin(User $user, User $temoin): bool
    {
        return $temoin->isPersonneAgee()
            && $temoin->statut === 'actif'
            && $temoin->approuve_par_id === $user->id;
    }

    /**
     * Vérifie si l'utilisateur est admin ou partage une thématique avec le témoin.
     */
    private function estLieAuTemoin(User $user, User $temoin): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $thematiqueIds = Thematique::visiblesPar($user)->pluck('id');

        return $temoin->thematiquesChoisies()->whereIn('thematiques.id', $thematiqueIds)->exists();
    }
}
