<?php

namespace App\Http\Requests\Groupe;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valide une transformation (rogner, pivoter, retourner) d'une photo de groupe.
 */
class EditerGroupeMediaRequest extends FormRequest
{
    /**
     * L'autorisation reste vérifiée dans le contrôleur (sera centralisée dans une Policy).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Retourne les règles de validation selon l'opération demandée.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'operation' => ['required', Rule::in(['crop', 'rotate', 'flip'])],
            // Crop
            'x' => ['required_if:operation,crop', 'nullable', 'integer', 'min:0'],
            'y' => ['required_if:operation,crop', 'nullable', 'integer', 'min:0'],
            'width' => ['required_if:operation,crop', 'nullable', 'integer', 'min:1'],
            'height' => ['required_if:operation,crop', 'nullable', 'integer', 'min:1'],
            // Rotate
            'angle' => ['required_if:operation,rotate', 'nullable', 'integer', Rule::in([90, 180, 270])],
            // Flip
            'direction' => ['required_if:operation,flip', 'nullable', Rule::in(['horizontal', 'vertical'])],
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
            'operation.required' => __('groupe_media.operation.required'),
            'operation.in' => __('groupe_media.operation.in'),
            'x.required_if' => __('groupe_media.x.required_if'),
            'x.integer' => __('groupe_media.x.integer'),
            'x.min' => __('groupe_media.x.min'),
            'y.required_if' => __('groupe_media.y.required_if'),
            'y.integer' => __('groupe_media.y.integer'),
            'y.min' => __('groupe_media.y.min'),
            'width.required_if' => __('groupe_media.width.required_if'),
            'width.integer' => __('groupe_media.width.integer'),
            'width.min' => __('groupe_media.width.min'),
            'height.required_if' => __('groupe_media.height.required_if'),
            'height.integer' => __('groupe_media.height.integer'),
            'height.min' => __('groupe_media.height.min'),
            'angle.required_if' => __('groupe_media.angle.required_if'),
            'angle.integer' => __('groupe_media.angle.integer'),
            'angle.in' => __('groupe_media.angle.in'),
            'direction.required_if' => __('groupe_media.direction.required_if'),
            'direction.in' => __('groupe_media.direction.in'),
        ];
    }
}
