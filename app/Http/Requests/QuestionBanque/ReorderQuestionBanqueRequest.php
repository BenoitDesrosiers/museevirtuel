<?php

namespace App\Http\Requests\QuestionBanque;

use App\Http\Requests\Commun\ReorderRequest;
use App\Http\Requests\Concerns\AutoriseGestionTypeProjet;

/**
 * Valide le réordonnancement des questions d'une section de banque de questions.
 */
class ReorderQuestionBanqueRequest extends ReorderRequest
{
    use AutoriseGestionTypeProjet;

    /**
     * Seul l'enseignant du cours (ou un admin) peut réordonner les questions, dans une section du type de projet.
     */
    public function authorize(): bool
    {
        if (! $this->peutGererTypeProjet()) {
            return false;
        }

        abort_if($this->route('section')->type_projet_id !== $this->route('typeProjet')->id, 404);

        return true;
    }
}
