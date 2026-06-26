<?php

namespace App\Policies;

use App\Models\Groupe;
use App\Models\User;

class GroupePolicy
{
    /**
     * Détermine si l'utilisateur peut consulter le groupe.
     *
     * Accessible aux membres, au témoin, à l'enseignant du cours et aux admins.
     */
    public function view(User $user, Groupe $groupe): bool
    {
        if ($user->isAdmin() || $groupe->classe->cours->enseignant_id === $user->id) {
            return true;
        }

        return $this->estMembreOuTemoin($user, $groupe);
    }

    /**
     * Détermine si l'utilisateur peut assigner un témoin au groupe.
     *
     * Réservé à l'enseignant du cours et aux admins.
     */
    public function assignerTemoin(User $user, Groupe $groupe): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isEnseignant() && $groupe->classe->cours->enseignant_id === $user->id;
    }

    /**
     * Détermine si l'utilisateur peut accéder aux échanges du groupe.
     *
     * Accessible aux membres, au témoin assigné, à l'enseignant du cours et aux admins.
     */
    public function echanges(User $user, Groupe $groupe): bool
    {
        if ($user->isAdmin() || $groupe->classe->cours->enseignant_id === $user->id) {
            return true;
        }

        return $this->estMembreOuTemoin($user, $groupe);
    }

    /**
     * Vérifie si l'utilisateur est membre du groupe ou le témoin assigné.
     */
    private function estMembreOuTemoin(User $user, Groupe $groupe): bool
    {
        if ($groupe->personne_agee_id === $user->id) {
            return true;
        }

        return $groupe->membres()->where('users.id', $user->id)->exists();
    }

    /**
     * Détermine si l'utilisateur peut gérer les membres du groupe.
     *
     * Réservé au créateur du groupe uniquement.
     */
    public function manageMembers(User $user, Groupe $groupe): bool
    {
        return $groupe->created_by === $user->id;
    }

    /**
     * Détermine si l'utilisateur peut gérer les thématiques du groupe.
     *
     * Accessible à tous les membres du groupe.
     */
    public function manageThematiques(User $user, Groupe $groupe): bool
    {
        return $groupe->membres()->where('users.id', $user->id)->exists();
    }

    /**
     * Détermine si l'utilisateur peut supprimer le groupe.
     *
     * Réservé à l'enseignant du cours et aux admins.
     */
    public function delete(User $user, Groupe $groupe): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->role === 'enseignant'
            && $groupe->classe->cours->enseignant_id === $user->id;
    }

    /**
     * Détermine si l'utilisateur peut ajouter une note au groupe.
     *
     * Accessible à tous les membres du groupe.
     */
    public function addNote(User $user, Groupe $groupe): bool
    {
        return $groupe->membres()->where('users.id', $user->id)->exists();
    }

    /**
     * Détermine si l'utilisateur peut uploader un média dans le groupe.
     *
     * Accessible à tous les membres du groupe.
     */
    public function addMedia(User $user, Groupe $groupe): bool
    {
        return $groupe->membres()->where('users.id', $user->id)->exists();
    }

    /**
     * Détermine si l'utilisateur peut éditer (rogner, pivoter, retourner) une photo du groupe.
     *
     * Accessible à tous les membres du groupe et à l'enseignant du cours.
     */
    public function editerMedia(User $user, Groupe $groupe): bool
    {
        return $this->estMembreOuEnseignantOuAdmin($user, $groupe);
    }

    /**
     * Détermine si l'utilisateur peut supprimer un média du groupe.
     *
     * L'enseignant du cours et les admins peuvent supprimer.
     */
    public function deleteMedia(User $user, Groupe $groupe): bool
    {
        return $user->isAdmin() || $groupe->classe->cours->enseignant_id === $user->id;
    }

    /**
     * Détermine si l'utilisateur peut lancer la transcription d'un message vocal.
     *
     * Accessible à tous les membres du groupe, à l'enseignant du cours et aux admins.
     */
    public function transcrireMedia(User $user, Groupe $groupe): bool
    {
        return $this->estMembreOuEnseignantOuAdmin($user, $groupe);
    }

    /**
     * Vérifie si l'utilisateur est membre du groupe, l'enseignant du cours ou un admin.
     *
     * Utilisé par les permissions qui suivent la même règle d'accès (éditer, transcrire…).
     */
    private function estMembreOuEnseignantOuAdmin(User $user, Groupe $groupe): bool
    {
        if ($user->isAdmin() || $groupe->classe->cours->enseignant_id === $user->id) {
            return true;
        }

        return $groupe->membres()->where('users.id', $user->id)->exists();
    }
}
