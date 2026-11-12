<?php

namespace App\Http\Requests\Groupe;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valide les coupes à appliquer à une vidéo de groupe.
 */
class EditerGroupeVideoRequest extends FormRequest
{
    /**
     * L'autorisation reste vérifiée dans le contrôleur.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Retourne les règles de validation des bornes de la vidéo et des coupes.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'debut' => ['required', 'numeric', 'min:0'],
            'fin' => ['required', 'numeric', 'gt:debut'],
            'coupes' => ['nullable', 'array'],
            'coupes.*.debut' => ['required', 'numeric', 'min:0'],
            'coupes.*.fin' => ['required', 'numeric', 'gt:coupes.*.debut'],
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
            'debut.required' => __('groupe_video.debut.required'),
            'debut.numeric' => __('groupe_video.debut.numeric'),
            'debut.min' => __('groupe_video.debut.min'),
            'fin.required' => __('groupe_video.fin.required'),
            'fin.numeric' => __('groupe_video.fin.numeric'),
            'fin.gt' => __('groupe_video.fin.gt'),
            'coupes.array' => __('groupe_video.coupes.array'),
            'coupes.*.debut.required' => __('groupe_video.coupes.debut.required'),
            'coupes.*.debut.numeric' => __('groupe_video.coupes.debut.numeric'),
            'coupes.*.debut.min' => __('groupe_video.coupes.debut.min'),
            'coupes.*.fin.required' => __('groupe_video.coupes.fin.required'),
            'coupes.*.fin.numeric' => __('groupe_video.coupes.fin.numeric'),
            'coupes.*.fin.gt' => __('groupe_video.coupes.fin.gt'),
        ];
    }
}
