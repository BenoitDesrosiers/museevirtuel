<?php

namespace App\Http\Requests\TypeProjet;

use App\Http\Requests\Concerns\AutoriseGestionTypeProjet;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation du basculement de visibilité d'un groupe de critères (positif ou négatif).
 */
class UpdateVisibiliteGroupeCritereRequest extends FormRequest
{
    use AutoriseGestionTypeProjet;

    /**
     * Seul l'enseignant du cours (ou un admin) peut changer la visibilité des critères.
     */
    public function authorize(): bool
    {
        return $this->peutGererTypeProjet();
    }

    /**
     * Retourne les règles de validation pour le basculement de visibilité d'un groupe de critères.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'in:positif,negatif'],
            'visible' => ['required', 'boolean'],
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
            'type.required' => __('visibilite_groupe_critere.type.required'),
            'type.in' => __('visibilite_groupe_critere.type.in'),
            'visible.required' => __('visibilite_groupe_critere.visible.required'),
            'visible.boolean' => __('visibilite_groupe_critere.visible.boolean'),
        ];
    }
}
