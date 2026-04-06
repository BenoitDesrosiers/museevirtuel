<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatePonderations;
use App\Models\TypeProjet;
use Illuminate\Foundation\Http\FormRequest;

class StoreGrilleCorrectionRequest extends FormRequest
{
    use ValidatePonderations;

    /**
     * Vérifie que l'enseignant est propriétaire du type de projet et qu'il n'a pas encore de grille.
     */
    public function authorize(): bool
    {
        /** @var TypeProjet $typeProjet */
        $typeProjet = $this->route('typeProjet');

        return $typeProjet->enseignant_id === auth()->id()
            && $typeProjet->grille === null;
    }

    /**
     * Règles de validation pour la création d'une grille de correction.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'criteres' => ['required', 'array', 'min:1'],
            'criteres.*.label' => ['required', 'string', 'max:255'],
            'criteres.*.ponderation' => ['required', 'integer', 'min:1', 'max:100'],
            'malus' => ['nullable', 'array'],
            'malus.*.label' => ['required', 'string', 'max:255'],
            'malus.*.deduction' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'malus.*.description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
