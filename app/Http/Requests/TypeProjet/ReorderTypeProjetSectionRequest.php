<?php

namespace App\Http\Requests\TypeProjet;

use App\Http\Requests\Commun\ReorderRequest;
use App\Http\Requests\Concerns\AutoriseGestionTypeProjet;

/**
 * Valide le réordonnancement des sections d'un type de projet.
 */
class ReorderTypeProjetSectionRequest extends ReorderRequest
{
    use AutoriseGestionTypeProjet;

    /**
     * Seul l'enseignant du cours (ou un admin) peut réordonner les sections.
     */
    public function authorize(): bool
    {
        return $this->peutGererTypeProjet();
    }
}
