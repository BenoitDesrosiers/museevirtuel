<?php

namespace App\Http\Requests\Projet;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valide le vote de remise d'un membre du groupe.
 */
class VoterProjetRemiseRequest extends FormRequest
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
     * Retourne les règles de validation du vote de remise.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'vote' => ['required', 'boolean'],
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
            'vote.required' => __('projet_remise.vote.required'),
            'vote.boolean' => __('projet_remise.vote.boolean'),
        ];
    }
}
