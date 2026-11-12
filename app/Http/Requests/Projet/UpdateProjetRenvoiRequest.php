<?php

namespace App\Http\Requests\Projet;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valide la modification d'un renvoi (endnote), y compris sa renumérotation.
 */
class UpdateProjetRenvoiRequest extends FormRequest
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
     * Retourne les règles de validation pour la modification d'un renvoi.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'contenu' => ['nullable', 'string', 'max:2000'],
            'numero' => ['sometimes', 'integer', 'min:1'],
            'type_reference' => ['sometimes', 'nullable', 'string', 'max:50'],
            'champs_reference' => ['sometimes', 'nullable', 'array'],
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
            'numero.integer' => __('projet_renvoi.numero.integer'),
            'numero.min' => __('projet_renvoi.numero.min'),
            'type_reference.string' => __('projet_renvoi.type_reference.string'),
            'type_reference.max' => __('projet_renvoi.type_reference.max'),
            'champs_reference.array' => __('projet_renvoi.champs_reference.array'),
        ];
    }
}
