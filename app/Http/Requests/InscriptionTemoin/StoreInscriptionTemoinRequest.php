<?php

namespace App\Http\Requests\InscriptionTemoin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Validation de l'étape 1 de l'inscription d'un témoin (identité, choix de cégeps et thématiques).
 */
class StoreInscriptionTemoinRequest extends FormRequest
{
    /**
     * Détermine si l'utilisateur est autorisé à faire cette requête.
     * L'autorisation reste vérifiée dans le contrôleur.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Retourne les règles de validation pour l'étape 1 de l'inscription d'un témoin.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'prenom' => ['required', 'string', 'max:255'],
            'nom' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            'choix' => ['required', 'array', 'min:1'],
            'choix.*.etablissement_id' => ['required', 'integer', 'exists:etablissements,id'],
            'choix.*.thematique_ids' => ['nullable', 'array'],
            'choix.*.thematique_ids.*' => ['integer', 'exists:thematiques,id'],
            'choix.*.theme_libre' => ['nullable', 'string', 'max:500'],
            'description' => ['required', 'string', 'max:2000'],
            'disponible_appel_distance' => ['boolean'],
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
            'prenom.required' => __('inscription_temoin.prenom.required'),
            'prenom.string' => __('inscription_temoin.prenom.string'),
            'prenom.max' => __('inscription_temoin.prenom.max'),
            'nom.required' => __('inscription_temoin.nom.required'),
            'nom.string' => __('inscription_temoin.nom.string'),
            'nom.max' => __('inscription_temoin.nom.max'),
            'email.required' => __('inscription_temoin.email.required'),
            'email.string' => __('inscription_temoin.email.string'),
            'email.email' => __('inscription_temoin.email.email'),
            'email.max' => __('inscription_temoin.email.max'),
            'email.unique' => __('inscription_temoin.email.unique'),
            'password.required' => __('inscription_temoin.password.required'),
            'password.string' => __('inscription_temoin.password.string'),
            'password.confirmed' => __('inscription_temoin.password.confirmed'),
            'choix.required' => __('inscription_temoin.choix.required'),
            'choix.array' => __('inscription_temoin.choix.array'),
            'choix.min' => __('inscription_temoin.choix.min'),
            'choix.*.etablissement_id.required' => __('inscription_temoin.choix.etablissement_id.required'),
            'choix.*.etablissement_id.integer' => __('inscription_temoin.choix.etablissement_id.integer'),
            'choix.*.etablissement_id.exists' => __('inscription_temoin.choix.etablissement_id.exists'),
            'choix.*.thematique_ids.array' => __('inscription_temoin.choix.thematique_ids.array'),
            'choix.*.thematique_ids.*.integer' => __('inscription_temoin.choix.thematique_ids.integer'),
            'choix.*.thematique_ids.*.exists' => __('inscription_temoin.choix.thematique_ids.exists'),
            'choix.*.theme_libre.string' => __('inscription_temoin.choix.theme_libre.string'),
            'choix.*.theme_libre.max' => __('inscription_temoin.choix.theme_libre.max'),
            'description.required' => __('inscription_temoin.description.required'),
            'description.string' => __('inscription_temoin.description.string'),
            'description.max' => __('inscription_temoin.description.max'),
            'disponible_appel_distance.boolean' => __('inscription_temoin.disponible_appel_distance.boolean'),
        ];
    }
}
