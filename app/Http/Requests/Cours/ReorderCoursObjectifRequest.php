<?php

namespace App\Http\Requests\Cours;

use App\Http\Requests\Commun\ReorderRequest;

/**
 * Valide le réordonnancement des objectifs d'un cours.
 */
class ReorderCoursObjectifRequest extends ReorderRequest
{
    /**
     * Seul l'enseignant du cours (ou un admin) peut réordonner les objectifs.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('cours'));
    }
}
