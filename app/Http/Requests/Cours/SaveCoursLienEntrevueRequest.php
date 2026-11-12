<?php

namespace App\Http\Requests\Cours;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valide la création et la mise à jour d'un lien d'entrevue de cours.
 */
class SaveCoursLienEntrevueRequest extends FormRequest
{
    /**
     * Seul l'enseignant du cours (ou un admin) peut gérer les liens d'entrevue ; en modification, le lien doit appartenir au cours.
     */
    public function authorize(): bool
    {
        $cours = $this->route('cours');

        if ($this->user()->cannot('update', $cours)) {
            return false;
        }

        $lien = $this->route('lien');
        abort_if($lien !== null && $lien->cours_id !== $cours->id, 404);

        return true;
    }

    /**
     * Retourne les règles de validation d'un lien d'entrevue.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:2048'],
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
            'label.required' => __('cours_lien_entrevue.label.required'),
            'label.string' => __('cours_lien_entrevue.label.string'),
            'label.max' => __('cours_lien_entrevue.label.max'),
            'url.required' => __('cours_lien_entrevue.url.required'),
            'url.url' => __('cours_lien_entrevue.url.url'),
            'url.max' => __('cours_lien_entrevue.url.max'),
        ];
    }
}
