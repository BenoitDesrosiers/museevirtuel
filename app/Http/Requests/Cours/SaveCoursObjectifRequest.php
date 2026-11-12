<?php

namespace App\Http\Requests\Cours;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valide la création et la mise à jour d'un objectif pédagogique de cours.
 */
class SaveCoursObjectifRequest extends FormRequest
{
    /**
     * Seul l'enseignant du cours (ou un admin) peut gérer les objectifs ; en modification, l'objectif doit appartenir au cours.
     */
    public function authorize(): bool
    {
        $cours = $this->route('cours');

        if ($this->user()->cannot('update', $cours)) {
            return false;
        }

        $objectif = $this->route('objectif');
        abort_if($objectif !== null && $objectif->cours_id !== $cours->id, 404);

        return true;
    }

    /**
     * Retourne les règles de validation d'un objectif pédagogique.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'contenu' => ['required', 'string', 'max:1000'],
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
            'contenu.required' => __('cours_objectif.contenu.required'),
            'contenu.string' => __('cours_objectif.contenu.string'),
            'contenu.max' => __('cours_objectif.contenu.max'),
        ];
    }
}
