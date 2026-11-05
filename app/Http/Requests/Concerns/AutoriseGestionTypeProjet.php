<?php

namespace App\Http\Requests\Concerns;

/**
 * Autorisation commune aux requêtes de gestion d'un type de projet (enseignant du cours ou admin).
 */
trait AutoriseGestionTypeProjet
{
    /**
     * Vérifie que le type de projet appartient au cours de l'URL (404),
     * puis que l'utilisateur peut le gérer (TypeProjetPolicy::update).
     */
    protected function peutGererTypeProjet(): bool
    {
        $typeProjet = $this->route('typeProjet');
        abort_if($typeProjet->cours_id !== $this->route('cours')->id, 404);

        return $this->user()->can('update', $typeProjet);
    }
}
