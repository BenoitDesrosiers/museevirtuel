<?php

namespace App\Policies;

use App\Models\TypeProjet;
use App\Models\User;

class TypeProjetPolicy
{
    /**
     * Détermine si l'utilisateur peut modifier ce type de projet.
     *
     * Réservé à l'enseignant propriétaire et aux admins.
     */
    public function update(User $user, TypeProjet $typeProjet): bool
    {
        return $user->isAdmin() || $typeProjet->enseignant_id === $user->id;
    }

    /**
     * Détermine si l'utilisateur peut supprimer ce type de projet.
     *
     * Réservé à l'enseignant propriétaire et aux admins.
     */
    public function delete(User $user, TypeProjet $typeProjet): bool
    {
        return $user->isAdmin() || $typeProjet->enseignant_id === $user->id;
    }
}
