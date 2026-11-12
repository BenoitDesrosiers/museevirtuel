<?php

namespace App\Http\Requests\Projet;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valide la création ou la modification de la correction d'un critère.
 */
class UpsertProjetCritereCorrectionRequest extends FormRequest
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
     * Retourne les règles de validation de la correction d'un critère.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'points' => ['nullable', 'numeric', 'min:0'],
            'commentaire' => ['nullable', 'string', 'max:2000'],
            'verifie' => ['boolean'],
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
            'user_id.integer' => __('projet_critere_correction.user_id.integer'),
            'user_id.exists' => __('projet_critere_correction.user_id.exists'),
            'points.numeric' => __('projet_critere_correction.points.numeric'),
            'points.min' => __('projet_critere_correction.points.min'),
            'commentaire.string' => __('projet_critere_correction.commentaire.string'),
            'commentaire.max' => __('projet_critere_correction.commentaire.max'),
            'verifie.boolean' => __('projet_critere_correction.verifie.boolean'),
        ];
    }
}
