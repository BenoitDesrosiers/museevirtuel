<?php

namespace App\Http\Requests\Projet;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valide l'upload d'une image pour le schéma visuel.
 */
class UploadProjetSchemaImageRequest extends FormRequest
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
     * Retourne les règles de validation de l'image du schéma visuel.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'image' => ['required', 'image', 'max:5120', 'mimes:jpg,jpeg,png,gif,webp'],
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
            'image.required' => __('projet_schema_image.image.required'),
            'image.image' => __('projet_schema_image.image.image'),
            'image.max' => __('projet_schema_image.image.max'),
            'image.mimes' => __('projet_schema_image.image.mimes'),
        ];
    }
}
