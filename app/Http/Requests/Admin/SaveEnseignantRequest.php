<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valide l'ajout et la mise à jour d'un enseignant.
 */
class SaveEnseignantRequest extends FormRequest
{
    /**
     * Le rôle admin est vérifié par le middleware de route.
     * En modification, la cible doit être un compte enseignant (UserPolicy::gererEnseignant).
     */
    public function authorize(): bool
    {
        $enseignant = $this->route('enseignant');

        return $enseignant === null || $this->user()->can('gererEnseignant', $enseignant);
    }

    /**
     * Retourne les règles de validation d'un enseignant.
     * En mise à jour, l'unicité du courriel ignore l'enseignant modifié.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var User|null $enseignant */
        $enseignant = $this->route('enseignant');

        return [
            'prenom' => ['required', 'string', 'max:255'],
            'nom' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)->ignore($enseignant?->id)],
            'etablissement_id' => ['nullable', 'integer', 'exists:etablissements,id'],
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
            'prenom.required' => __('enseignant.prenom.required'),
            'prenom.string' => __('enseignant.prenom.string'),
            'prenom.max' => __('enseignant.prenom.max'),
            'nom.required' => __('enseignant.nom.required'),
            'nom.string' => __('enseignant.nom.string'),
            'nom.max' => __('enseignant.nom.max'),
            'email.required' => __('enseignant.email.required'),
            'email.string' => __('enseignant.email.string'),
            'email.email' => __('enseignant.email.email'),
            'email.max' => __('enseignant.email.max'),
            'email.unique' => __('enseignant.email.unique'),
            'etablissement_id.integer' => __('enseignant.etablissement_id.integer'),
            'etablissement_id.exists' => __('enseignant.etablissement_id.exists'),
        ];
    }
}
