<?php

namespace App\Policies;

use App\Models\TypeProjet;
use App\Models\User;

/**
 * Règles d'accès à un type de projet et à sa grille
 * (sections, critères, tâches, banque de questions).
 *
 * Le type de projet appartient au cours : c'est l'enseignant du cours qui le gère,
 */
readonly class TypeProjetPolicy
{
    /**
     * Injecte la policy du cours, qui définit qui gère le cours.
     */
    public function __construct(private CoursPolicy $coursPolicy) {}

    /**
     * Détermine si l'utilisateur peut modifier ce type de projet ou sa grille.
     *
     * Réservé à l'enseignant du cours et aux admins.
     */
    public function update(User $user, TypeProjet $typeProjet): bool
    {
        return $this->coursPolicy->update($user, $typeProjet->cours);
    }

    /**
     * Détermine si l'utilisateur peut supprimer ce type de projet.
     *
     * Réservé à l'enseignant du cours et aux admins.
     */
    public function delete(User $user, TypeProjet $typeProjet): bool
    {
        return $this->update($user, $typeProjet);
    }
}
