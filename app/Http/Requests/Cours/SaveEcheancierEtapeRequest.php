<?php

namespace App\Http\Requests\Cours;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valide la création et la mise à jour d'une étape de l'échéancier d'un cours.
 */
class SaveEcheancierEtapeRequest extends FormRequest
{
    /**
     * En modification, l'étape doit appartenir au cours ; seul l'enseignant du cours (ou un admin) peut gérer l'échéancier.
     */
    public function authorize(): bool
    {
        $cours = $this->route('cours');
        $etape = $this->route('etape');
        abort_if($etape !== null && $etape->cours_id !== $cours->id, 404);

        return $this->user()->can('update', $cours);
    }

    /**
     * Retourne les règles de validation d'une étape de l'échéancier.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'semaine' => ['required', 'integer', 'min:1', 'max:15'],
            'periode' => ['nullable', 'integer', 'in:1,2'],
            'etape' => ['required', 'string', 'max:500'],
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
            'semaine.required' => __('echeancier_etape.semaine.required'),
            'semaine.integer' => __('echeancier_etape.semaine.integer'),
            'semaine.min' => __('echeancier_etape.semaine.min'),
            'semaine.max' => __('echeancier_etape.semaine.max'),
            'periode.integer' => __('echeancier_etape.periode.integer'),
            'periode.in' => __('echeancier_etape.periode.in'),
            'etape.required' => __('echeancier_etape.etape.required'),
            'etape.string' => __('echeancier_etape.etape.string'),
            'etape.max' => __('echeancier_etape.etape.max'),
        ];
    }
}
