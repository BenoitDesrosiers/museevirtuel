<?php

namespace App\Http\Requests\Groupe;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valide l'ajout et le retrait de membres d'un groupe.
 */
class UpdateGroupeMembreRequest extends FormRequest
{
    /**
     * L'autorisation reste vérifiée dans le contrôleur.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Retourne les règles de validation des listes de membres à ajouter et à retirer.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'ajouter' => ['array'],
            'ajouter.*' => ['integer', 'exists:users,id'],
            'retirer' => ['array'],
            'retirer.*' => ['integer', 'exists:users,id'],
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
            'ajouter.array' => __('groupe_membre.ajouter.array'),
            'ajouter.*.integer' => __('groupe_membre.ajouter.integer'),
            'ajouter.*.exists' => __('groupe_membre.ajouter.exists'),
            'retirer.array' => __('groupe_membre.retirer.array'),
            'retirer.*.integer' => __('groupe_membre.retirer.integer'),
            'retirer.*.exists' => __('groupe_membre.retirer.exists'),
        ];
    }
}
