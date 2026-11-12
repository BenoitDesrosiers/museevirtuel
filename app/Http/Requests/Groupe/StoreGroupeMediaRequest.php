<?php

namespace App\Http\Requests\Groupe;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valide l'upload d'un média (photo, audio, document) dans un groupe.
 */
class StoreGroupeMediaRequest extends FormRequest
{
    /**
     * L'autorisation reste vérifiée dans le contrôleur (sera centralisée dans une Policy).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Retourne les règles de validation du fichier média uploadé.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'fichier' => ['required', 'file', 'max:51200', 'mimes:jpg,jpeg,png,webp,gif,pdf,doc,docx,mp3,wav,ogg,m4a,aac',
            ],
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
            'fichier.required' => __('groupe_media.fichier.required'),
            'fichier.file' => __('groupe_media.fichier.file'),
            'fichier.max' => __('groupe_media.fichier.max'),
            'fichier.mimes' => __('groupe_media.fichier.mimes'),
        ];
    }
}
