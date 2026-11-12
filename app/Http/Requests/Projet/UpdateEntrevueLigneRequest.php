<?php

namespace App\Http\Requests\Projet;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valide la modification d'une ligne d'entrevue.
 */
class UpdateEntrevueLigneRequest extends FormRequest
{
    /**
     * Détermine si l'utilisateur est autorisé à faire cette requête.
     * L'autorisation reste vérifiée dans le contrôleur.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Retourne les règles de validation d'une ligne d'entrevue.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'dimension' => ['nullable', 'string'],
            'indicateur' => ['nullable', 'string'],
            'questions' => ['nullable', 'array'],
            'questions.*' => ['string'],
        ];
    }

    /**
     * Retourne les messages d'erreur de validation personnalisés.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'dimension.string' => __('entrevue_ligne.dimension.string'),
            'indicateur.string' => __('entrevue_ligne.indicateur.string'),
            'questions.array' => __('entrevue_ligne.questions.array'),
            'questions.*.string' => __('entrevue_ligne.questions.string'),
        ];
    }
}
