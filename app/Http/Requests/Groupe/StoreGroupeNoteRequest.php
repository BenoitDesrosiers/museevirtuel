<?php

namespace App\Http\Requests\Groupe;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valide l'ajout d'une note collaborative à un groupe.
 */
class StoreGroupeNoteRequest extends FormRequest
{
    /**
     * L'autorisation reste vérifiée dans le contrôleur (sera centralisée dans une Policy).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Retourne les règles de validation pour une nouvelle note.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'contenu' => ['required', 'string', 'max:2000'],
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
            'contenu.required' => __('groupe_note.contenu.required'),
            'contenu.string' => __('groupe_note.contenu.string'),
            'contenu.max' => __('groupe_note.contenu.max'),
        ];
    }
}
