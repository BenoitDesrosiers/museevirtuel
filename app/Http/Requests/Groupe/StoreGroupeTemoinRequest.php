<?php

namespace App\Http\Requests\Groupe;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valide l'assignation ou la désassignation d'un témoin (personne âgée) à un groupe.
 */
class StoreGroupeTemoinRequest extends FormRequest
{
    /**
     * L'autorisation reste vérifiée dans le contrôleur.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Retourne les règles de validation du témoin assigné (personne âgée active).
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'personne_agee_id' => ['nullable', 'integer',
                Rule::exists('users', 'id')->where('role', 'personne_agee')->where('statut', 'actif'),
            ],
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
            'personne_agee_id.integer' => __('groupe_temoin.personne_agee_id.integer'),
            'personne_agee_id.exists' => __('groupe_temoin.personne_agee_id.exists'),
        ];
    }
}
