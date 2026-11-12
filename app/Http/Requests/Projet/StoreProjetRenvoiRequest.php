<?php

namespace App\Http\Requests\Projet;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valide la création d'un renvoi (endnote).
 */
class StoreProjetRenvoiRequest extends FormRequest
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
     * Retourne les règles de validation pour la création d'un renvoi.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'contenu' => ['nullable', 'string', 'max:2000'],
            'type_reference' => ['nullable', 'string', 'max:50'],
            'champs_reference' => ['nullable', 'array'],
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
            'contenu.string' => __('projet_renvoi.contenu.string'),
            'contenu.max' => __('projet_renvoi.contenu.max'),
            'type_reference.string' => __('projet_renvoi.type_reference.string'),
            'type_reference.max' => __('projet_renvoi.type_reference.max'),
            'champs_reference.array' => __('projet_renvoi.champs_reference.array'),
        ];
    }
}
