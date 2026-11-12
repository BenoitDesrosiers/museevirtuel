<?php

namespace App\Http\Requests\Groupe;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valide le jumelage (insertion) d'une autre vidéo dans une vidéo de groupe.
 */
class JumelerGroupeVideoRequest extends FormRequest
{
    /**
     * L'autorisation reste vérifiée dans le contrôleur.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Retourne les règles de validation de la vidéo à insérer et de sa position.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'video_a_inserer_id' => ['required', 'integer', 'exists:groupe_videos,id'],
            'position' => ['required', 'numeric', 'min:0'],
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
            'video_a_inserer_id.required' => __('groupe_video.video_a_inserer_id.required'),
            'video_a_inserer_id.integer' => __('groupe_video.video_a_inserer_id.integer'),
            'video_a_inserer_id.exists' => __('groupe_video.video_a_inserer_id.exists'),
            'position.required' => __('groupe_video.position.required'),
            'position.numeric' => __('groupe_video.position.numeric'),
            'position.min' => __('groupe_video.position.min'),
        ];
    }
}
