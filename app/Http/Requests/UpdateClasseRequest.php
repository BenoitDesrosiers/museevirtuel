<?php

namespace App\Http\Requests;

use App\Models\Classe;
use Illuminate\Foundation\Http\FormRequest;

class UpdateClasseRequest extends FormRequest
{
    /**
     * Détermine si l'utilisateur est autorisé à modifier cette classe.
     * Délègue à ClassePolicy::update() — réservé à l'enseignant du cours.
     */
    public function authorize(): bool
    {
        /** @var Classe $classe */
        $classe = $this->route('classe');

        return $this->user()->can('update', $classe);
    }

    /**
     * Retourne les règles de validation pour la mise à jour d'une classe.
     * Le code peut être modifié librement par le prof (max 20 caractères).
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20'],
        ];
    }
}
