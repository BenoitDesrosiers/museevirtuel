<?php

namespace App\Http\Requests\Groupe;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valide la suppression d'une correction inline sur une note de groupe.
 */
class DestroyGroupeNoteCorrectionRequest extends FormRequest
{
    /**
     * L'autorisation reste vérifiée dans le contrôleur (sera centralisée dans une Policy).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Retourne les règles de validation du HTML mis à jour de la note.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'note_html' => ['required', 'string'],
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
            'note_html.required' => __('groupe_note_correction.note_html.required'),
            'note_html.string' => __('groupe_note_correction.note_html.string'),
        ];
    }
}
