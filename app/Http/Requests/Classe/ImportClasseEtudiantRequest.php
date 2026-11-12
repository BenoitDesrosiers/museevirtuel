<?php

namespace App\Http\Requests\Classe;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valide l'import CSV d'étudiants dans une section.
 */
class ImportClasseEtudiantRequest extends FormRequest
{
    /**
     * La classe doit appartenir au cours, que l'utilisateur doit pouvoir modifier.
     */
    public function authorize(): bool
    {
        $cours = $this->route('cours');
        abort_if($this->route('classe')->cours_id !== $cours->id, 404);

        return $this->user()->can('update', $cours);
    }

    /**
     * Retourne les règles de validation du fichier CSV d'import.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'csv' => ['required', 'file', 'mimes:csv,txt'],
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
            'csv.required' => __('classe_etudiant.csv.required'),
            'csv.file' => __('classe_etudiant.csv.file'),
            'csv.mimes' => __('classe_etudiant.csv.mimes'),
        ];
    }
}
