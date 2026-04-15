<?php

namespace App\Policies;

use App\Models\Classe;
use App\Models\User;

class ClassePolicy
{
    /**
     * Détermine si l'utilisateur peut consulter la classe (section de cours).
     *
     * Accessible aux étudiants inscrits, à l'enseignant du cours et aux admins.
     */
    public function view(User $user, Classe $classe): bool
    {
        if ($user->isAdmin() || $classe->cours->enseignant_id === $user->id) {
            return true;
        }

        return $classe->etudiants()->where('users.id', $user->id)->exists();
    }

    /**
     * Détermine si l'utilisateur peut modifier la classe (section) ou gérer ses étudiants.
     *
     * Réservé à l'enseignant du cours et aux admins.
     */
    public function update(User $user, Classe $classe): bool
    {
        return $user->isAdmin() || $classe->cours->enseignant_id === $user->id;
    }

    /**
     * Détermine si l'utilisateur peut supprimer la classe (section).
     *
     * Réservé à l'enseignant du cours et aux admins.
     */
    public function delete(User $user, Classe $classe): bool
    {
        return $user->isAdmin() || $classe->cours->enseignant_id === $user->id;
    }
}
