<?php

namespace App\Http\Requests\Projet;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valide l'ajout d'un média (fichier ou URL) à une section de projet.
 */
class StoreProjetSectionMediaRequest extends FormRequest
{
    /**
     * Seuls les membres du groupe peuvent ajouter un média, dans une chaîne d'URL cohérente.
     */
    public function authorize(): bool
    {
        $classe = $this->route('classe');
        $groupe = $this->route('groupe');

        abort_if($classe->cours_id !== $this->route('cours')->id, 404);
        abort_if($groupe->classe_id !== $classe->id, 404);
        abort_if($this->route('section')->type_projet_id !== $this->route('typeProjet')->id, 404);

        return $groupe->membres()->where('user_id', $this->user()->id)->exists();
    }

    /**
     * Retourne les règles de validation du média de section.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'source_type' => ['required', 'in:upload,url'],
            'url' => ['required_if:source_type,url', 'nullable', 'url', 'max:2048'],
            'fichier' => ['required_if:source_type,upload', 'nullable', 'max:204800',
                'mimes:mp4,webm,ogg,mp3,wav,m4a,aac',
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
            'source_type.required' => __('projet_section_media.source_type.required'),
            'source_type.in' => __('projet_section_media.source_type.in'),
            'url.required_if' => __('projet_section_media.url.required_if'),
            'url.url' => __('projet_section_media.url.url'),
            'url.max' => __('projet_section_media.url.max'),
            'fichier.required_if' => __('projet_section_media.fichier.required_if'),
            'fichier.max' => __('projet_section_media.fichier.max'),
            'fichier.mimes' => __('projet_section_media.fichier.mimes'),
        ];
    }
}
