<?php

namespace App\Http\Requests\Groupe;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valide l'upload d'une vidéo de groupe.
 */
class StoreGroupeVideoRequest extends FormRequest
{
    /**
     * L'autorisation reste vérifiée dans le contrôleur.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Retourne les règles de validation du titre, de la description et du fichier vidéo.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'titre' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'fichier' => ['required', 'max:2097152', 'mimes:mp4,webm,mov,avi,mkv'],
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
            'fichier.required' => __('groupe_video.fichier.required'),
            'fichier.max' => __('groupe_video.fichier.max'),
            'fichier.mimes' => __('groupe_video.fichier.mimes'),
        ];
    }
}
