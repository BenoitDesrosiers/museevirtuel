<?php

namespace App\Policies;

use App\Models\Groupe;
use App\Models\GroupeVideo;
use App\Models\User;

class GroupeVideoPolicy
{
    /**
     * Détermine si l'utilisateur peut publier une vidéo dans le groupe.
     *
     * Accessible aux membres du groupe lorsque le cours n'est pas verrouillé.
     */
    public function create(User $user, Groupe $groupe): bool
    {
        if (! $groupe->membres()->where('users.id', $user->id)->exists()) {
            return false;
        }

        // Un cours verrouillé interdit toute nouvelle publication.
        return ! $groupe->classe->cours->verrouille;
    }

    /**
     * Détermine si l'utilisateur peut consulter la vidéo.
     *
     * - Brouillon → auteur ou enseignant/admin seulement.
     * - Publié    → tout membre du groupe ou enseignant/admin.
     */
    public function view(User $user, GroupeVideo $video): bool
    {
        $groupe = $video->groupe;

        if ($user->isAdmin() || $groupe->classe->cours->enseignant_id === $user->id) {
            return true;
        }

        if ($video->statut === 'brouillon') {
            return $video->user_id === $user->id;
        }

        return $groupe->membres()->where('users.id', $user->id)->exists();
    }

    /**
     * Détermine si l'utilisateur peut modifier les métadonnées de la vidéo.
     *
     * Réservé à l'auteur et à l'enseignant du cours.
     */
    public function update(User $user, GroupeVideo $video): bool
    {
        return $this->estAuteurOuEnseignantOuAdmin($user, $video);
    }

    /**
     * Détermine si l'utilisateur peut supprimer la vidéo.
     *
     * Réservé à l'auteur et à l'enseignant du cours.
     */
    public function delete(User $user, GroupeVideo $video): bool
    {
        return $this->estAuteurOuEnseignantOuAdmin($user, $video);
    }

    /**
     * Détermine si l'utilisateur peut publier la vidéo (passer de brouillon à publié).
     *
     * Réservé à l'auteur et à l'enseignant du cours.
     */
    public function publier(User $user, GroupeVideo $video): bool
    {
        return $this->estAuteurOuEnseignantOuAdmin($user, $video);
    }

    /**
     * Détermine si l'utilisateur peut jumeler une vidéo avec une autre du même groupe.
     *
     * Même règles que editer() : membres du groupe, enseignant ou admin.
     * La vidéo ne doit pas être en cours de traitement.
     */
    public function jumeler(User $user, GroupeVideo $video): bool
    {
        if ($video->isBeingProcessed()) {
            return false;
        }

        $groupe = $video->groupe;

        if ($user->isAdmin() || $groupe->classe->cours->enseignant_id === $user->id) {
            return true;
        }

        return $groupe->membres()->where('users.id', $user->id)->exists();
    }

    /**
     * Vérifie si l'utilisateur est l'auteur de la vidéo, l'enseignant du cours ou un admin.
     *
     * Factorisé ici car update, delete et publier partagent exactement cette condition.
     */
    private function estAuteurOuEnseignantOuAdmin(User $user, GroupeVideo $video): bool
    {
        $groupe = $video->groupe;

        return $user->isAdmin()
            || $groupe->classe->cours->enseignant_id === $user->id
            || $video->user_id === $user->id;
    }

    /**
     * Détermine si l'utilisateur peut lancer la transcription Whisper d'une vidéo.
     *
     * Réservé à l'auteur de la vidéo et à l'enseignant du cours.
     * La transcription ne doit pas déjà être en cours.
     */
    public function transcrire(User $user, GroupeVideo $video): bool
    {
        return $this->estAuteurOuEnseignantOuAdmin($user, $video);
    }

    /**
     * Détermine si l'utilisateur peut modifier manuellement la transcription d'une vidéo.
     *
     * Réservé à l'auteur, à l'enseignant du cours et aux admins.
     */
    public function modifierTranscription(User $user, GroupeVideo $video): bool
    {
        return $this->estAuteurOuEnseignantOuAdmin($user, $video);
    }

    /**
     * Détermine si l'utilisateur peut créer, modifier ou supprimer des chapitres.
     *
     * Membres du groupe, enseignant et admins — les chapitres sont
     * une navigation collaborative, pas seulement réservée à l'auteur.
     */
    public function gererChapitres(User $user, GroupeVideo $video): bool
    {
        $groupe = $video->groupe;

        if ($user->isAdmin() || $groupe->classe->cours->enseignant_id === $user->id) {
            return true;
        }

        return $groupe->membres()->where('users.id', $user->id)->exists();
    }

    /**
     * Détermine si l'utilisateur peut soumettre des coupes FFmpeg sur la vidéo.
     *
     * Accessible à tous les membres du groupe et à l'enseignant du cours.
     * La vidéo ne doit pas être en cours de traitement.
     */
    public function editer(User $user, GroupeVideo $video): bool
    {
        if ($video->isBeingProcessed()) {
            return false;
        }

        $groupe = $video->groupe;

        if ($user->isAdmin() || $groupe->classe->cours->enseignant_id === $user->id) {
            return true;
        }

        return $groupe->membres()->where('users.id', $user->id)->exists();
    }
}
