<?php

namespace App\Http\Requests\Groupe;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valide le remplacement des thématiques d'un groupe.
 */
class UpdateGroupeThematiqueRequest extends FormRequest
{
    /**
     * L'autorisation reste vérifiée dans le contrôleur.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Retourne les règles de validation de la liste des thématiques.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'thematiques' => ['array', 'max:3'],
            'thematiques.*' => ['integer', 'exists:thematiques,id'],
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
            'thematiques.array' => __('groupe_thematique.thematiques.array'),
            'thematiques.max' => __('groupe_thematique.thematiques.max'),
            'thematiques.*.integer' => __('groupe_thematique.thematiques.integer'),
            'thematiques.*.exists' => __('groupe_thematique.thematiques.exists'),
        ];
    }
}
