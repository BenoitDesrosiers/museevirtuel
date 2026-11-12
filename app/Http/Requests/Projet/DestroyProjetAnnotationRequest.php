<?php

namespace App\Http\Requests\Projet;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valide la suppression d'une annotation inline (champ ciblé et HTML mis à jour).
 */
class DestroyProjetAnnotationRequest extends FormRequest
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
     * Retourne les règles de validation pour la suppression d'une annotation inline.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'champ' => ['required', 'string', 'regex:'.UpsertProjetAnnotationRequest::CHAMP_ANNOTABLE_REGEX],
            'html' => ['required', 'string'],
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
            'champ.required' => __('projet_annotation.champ.required'),
            'champ.string' => __('projet_annotation.champ.string'),
            'champ.regex' => __('projet_annotation.champ.regex'),
            'html.required' => __('projet_annotation.html.required'),
            'html.string' => __('projet_annotation.html.string'),
        ];
    }
}
