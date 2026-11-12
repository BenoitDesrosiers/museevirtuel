<?php

namespace App\Http\Requests\Groupe;

use App\Models\Groupe;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Valide la création d'un groupe dans une classe.
 */
class StoreGroupeRequest extends FormRequest
{
    /**
     * Un étudiant inscrit, l'enseignant du cours peut créer un groupe.
     */
    public function authorize(): bool
    {
        $classe = $this->route('classe');
        abort_if($classe->cours_id !== $this->route('cours')->id, 404);

        return $this->user()->can('create', [Groupe::class, $classe]);
    }

    /**
     * Retourne les règles de validation pour la création d'un groupe (membres et thématiques).
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'membres' => ['array'],
            'membres.*' => ['integer', 'exists:users,id', 'min:1'],
            'thematiques' => ['required', 'array', 'min:1', 'max:3'],
            'thematiques.*' => ['integer', 'exists:thematiques,id'],
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
            'membres.array' => __('groupe.membres.array'),
            'membres.*.integer' => __('groupe.membres.integer'),
            'membres.*.exists' => __('groupe.membres.exists'),
            'thematiques.required' => __('groupe.thematiques.required'),
            'thematiques.array' => __('groupe.thematiques.array'),
            'thematiques.min' => __('groupe.thematiques.min'),
            'thematiques.max' => __('groupe.thematiques.max'),
            'thematiques.*.integer' => __('groupe.thematiques.integer'),
            'thematiques.*.exists' => __('groupe.thematiques.exists'),
        ];
    }
}
