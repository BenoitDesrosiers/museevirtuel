<?php

namespace App\Http\Requests\Groupe;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valide la mise à jour du titre et de la description d'une vidéo de groupe.
 */
class UpdateGroupeVideoRequest extends FormRequest
{
    /**
     * L'autorisation reste vérifiée dans le contrôleur.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Retourne les règles de validation du titre et de la description.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'titre' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
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
            'titre.required' => __('groupe_video.titre.required'),
            'titre.string' => __('groupe_video.titre.string'),
            'titre.max' => __('groupe_video.titre.max'),
            'description.string' => __('groupe_video.description.string'),
            'description.max' => __('groupe_video.description.max'),
        ];
    }
}
