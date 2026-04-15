<?php

namespace App\Policies;

use App\Models\Cours;
use App\Models\User;

class CoursPolicy
{
    /**
     * Détermine si l'utilisateur peut consulter le cours.
     */
    public function view(User $user, Cours $cours): bool
    {
        return $user->isAdmin() || $cours->enseignant_id === $user->id;
    }

    /**
     * Détermine si l'utilisateur peut modifier le cours ou gérer ses étudiants.
     */
    public function update(User $user, Cours $cours): bool
    {
        return $user->isAdmin() || $cours->enseignant_id === $user->id;
    }

    /**
     * Détermine si l'utilisateur peut supprimer le cours.
     */
    public function delete(User $user, Cours $cours): bool
    {
        return $user->isAdmin() || $cours->enseignant_id === $user->id;
    }
}
