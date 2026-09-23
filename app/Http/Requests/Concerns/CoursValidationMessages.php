<?php

namespace App\Http\Requests\Concerns;

/**
 * Messages de validation en français pour la création et la mise à jour d'un cours.
 */
trait CoursValidationMessages
{
    /**
     * Retourne les messages d'erreur de validation en français.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nom_cours.required' => 'Le nom du cours est obligatoire.',
            'code.required' => 'Le code de cours est obligatoire.',
            'groupe.required' => 'Le groupe est obligatoire.',
            'annee.required' => 'L\'année est obligatoire.',
            'annee.integer' => 'L\'année doit être un nombre entier.',
            'annee.min' => 'L\'année doit être comprise entre 2000 et 2100.',
            'annee.max' => 'L\'année doit être comprise entre 2000 et 2100.',
            'session.required' => 'La session est obligatoire.',
            'type_cours.required' => 'Veuillez sélectionner un niveau de cours.',
            'taille_equipe_max.gte' => 'La taille maximale de l\'équipe doit être supérieure ou égale à la taille minimale.',
        ];
    }
}
