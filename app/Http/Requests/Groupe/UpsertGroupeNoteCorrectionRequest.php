<?php

namespace App\Http\Requests\Groupe;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valide la création ou la mise à jour d'une correction inline sur une note de groupe.
 */
class UpsertGroupeNoteCorrectionRequest extends FormRequest
{
    /**
     * L'autorisation reste vérifiée dans le contrôleur.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Retourne les règles de validation de la correction et du HTML de la note.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'commentaire_id' => ['required', 'string', 'max:36'],
            'contenu' => ['required', 'string', 'max:1000'],
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
            'commentaire_id.required' => __('groupe_note_correction.commentaire_id.required'),
            'commentaire_id.string' => __('groupe_note_correction.commentaire_id.string'),
            'commentaire_id.max' => __('groupe_note_correction.commentaire_id.max'),
            'contenu.required' => __('groupe_note_correction.contenu.required'),
            'contenu.string' => __('groupe_note_correction.contenu.string'),
            'contenu.max' => __('groupe_note_correction.contenu.max'),
            'note_html.required' => __('groupe_note_correction.note_html.required'),
            'note_html.string' => __('groupe_note_correction.note_html.string'),
        ];
    }
}
