<?php

namespace App\Http\Requests\Groupe;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valide la création et la mise à jour d'un chapitre de vidéo de groupe.
 */
class SaveGroupeVideoChapitreRequest extends FormRequest
{
    /**
     * L'autorisation reste vérifiée dans le contrôleur (sera centralisée dans une Policy).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Retourne les règles de validation du label et des bornes temporelles du chapitre.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:255'],
            'debut' => ['required', 'numeric', 'min:0'],
            'fin' => ['nullable', 'numeric', 'gt:debut'],
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
            'label.required' => __('groupe_video_chapitre.label.required'),
            'label.string' => __('groupe_video_chapitre.label.string'),
            'label.max' => __('groupe_video_chapitre.label.max'),
            'debut.required' => __('groupe_video_chapitre.debut.required'),
            'debut.numeric' => __('groupe_video_chapitre.debut.numeric'),
            'debut.min' => __('groupe_video_chapitre.debut.min'),
            'fin.numeric' => __('groupe_video_chapitre.fin.numeric'),
            'fin.gt' => __('groupe_video_chapitre.fin.gt'),
        ];
    }
}
