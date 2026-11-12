<?php

namespace App\Policies;

use App\Models\EtudiantReference;
use App\Models\User;

/**
 * Règles d'accès à la liste de références personnelle d'un étudiant.
 */
class EtudiantReferencePolicy
{
    /**
     * Détermine si l'utilisateur peut supprimer la référence.
     *
     * Réservé au propriétaire de la liste.
     */
    public function delete(User $user, EtudiantReference $reference): bool
    {
        return $reference->user_id === $user->id;
    }
}
