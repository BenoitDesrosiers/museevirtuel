<?php

namespace App\Http\Requests\TypeProjet;

use App\Http\Requests\Commun\ReorderRequest;
use App\Http\Requests\Concerns\AutoriseGestionTypeProjet;

/**
 * Valide le réordonnancement des tâches d'un type de projet.
 */
class ReorderTypeProjetTacheRequest extends ReorderRequest
{
    use AutoriseGestionTypeProjet;

    /**
     * Seul l'enseignant du cours (ou un admin) peut réordonner les tâches.
     */
    public function authorize(): bool
    {
        return $this->peutGererTypeProjet();
    }
}
