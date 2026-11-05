<?php

namespace App\Policies;

use App\Models\Thematique;
use App\Models\User;

/**
 * Règles d'accès d'un utilisateur sur un autre utilisateur.
 *
 * Couvre pour l'instant la gestion des témoins (personnes âgées).
 */
class UserPolicy
{
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
