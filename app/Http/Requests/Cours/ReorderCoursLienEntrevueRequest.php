<?php

namespace App\Http\Requests\Cours;

use App\Http\Requests\Commun\ReorderRequest;

/**
 * Valide le réordonnancement des liens d'entrevue d'un cours.
 */
class ReorderCoursLienEntrevueRequest extends ReorderRequest
{
    /**
     * Seul l'enseignant du cours (ou un admin) peut réordonner les liens d'entrevue.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('cours'));
    }
}
