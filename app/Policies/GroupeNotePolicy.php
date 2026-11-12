<?php

namespace App\Policies;

use App\Models\GroupeNote;
use App\Models\User;

/**
 * Règles d'accès aux notes collaboratives d'un groupe.
 *
 * La création d'une note se décide sur le groupe (GroupePolicy::addNote) ;
 * cette policy couvre ce qu'on fait ensuite sur une note existante.
 */
readonly class GroupeNotePolicy
{
    /**
     * Injecte la policy du cours, qui définit qui gère le cours.
     */
    public function __construct(private CoursPolicy $coursPolicy) {}

    /**
     * Détermine si l'utilisateur peut supprimer la note.
     *
     * Réservé à l'auteur de la note.
     */
    public function delete(User $user, GroupeNote $note): bool
    {
        return $note->user_id === $user->id;
    }

    /**
     * Détermine si l'utilisateur peut ajouter ou retirer des corrections inline sur la note.
     *
     * Réservé à l'enseignant du cours et aux admins.
     */
    public function corriger(User $user, GroupeNote $note): bool
    {
        return $this->coursPolicy->update($user, $note->groupe->classe->cours);
    }
}
