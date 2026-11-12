<?php

namespace App\Http\Requests\Cours;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valide la création et la mise à jour d'une référence bibliographique de cours.
 */
class SaveCoursReferenceRequest extends FormRequest
{
    /**
     * Seul l'enseignant du cours (ou un admin) peut gérer les références ; en modification, la référence doit appartenir au cours.
     */
    public function authorize(): bool
    {
        $cours = $this->route('cours');

        if ($this->user()->cannot('update', $cours)) {
            return false;
        }

        $reference = $this->route('reference');
        abort_if($reference !== null && $reference->cours_id !== $cours->id, 404);

        return true;
    }

    /**
     * Retourne les règles de validation d'une référence bibliographique.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:255'],
            'url' => ['nullable', 'url', 'max:500'],
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
            'nom.required' => __('cours_reference.nom.required'),
            'nom.string' => __('cours_reference.nom.string'),
            'nom.max' => __('cours_reference.nom.max'),
            'url.url' => __('cours_reference.url.url'),
            'url.max' => __('cours_reference.url.max'),
        ];
    }
}
