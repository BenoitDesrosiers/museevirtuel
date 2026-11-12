<?php

namespace App\Http\Requests\Cours;

use App\Http\Requests\Commun\ReorderRequest;

/**
 * Valide le réordonnancement des références d'un cours.
 */
class ReorderCoursReferenceRequest extends ReorderRequest
{
    /**
     * Seul l'enseignant du cours (ou un admin) peut réordonner les références.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('cours'));
    }
}
