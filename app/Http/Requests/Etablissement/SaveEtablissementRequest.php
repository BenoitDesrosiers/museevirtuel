<?php

namespace App\Http\Requests\Etablissement;

use App\Models\Etablissement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valide la création et la mise à jour d'un établissement.
 */
class SaveEtablissementRequest extends FormRequest
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
     * Retourne les règles de validation d'un établissement.
     * En mise à jour, l'unicité du code ignore l'établissement modifié.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Etablissement|null $etablissement */
        $etablissement = $this->route('etablissement');

        return [
            'nom' => ['required', 'string', 'max:255'],
            'ville' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('etablissements', 'code')->ignore($etablissement?->id)],
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
            'nom.required' => __('etablissement.nom.required'),
            'nom.string' => __('etablissement.nom.string'),
            'nom.max' => __('etablissement.nom.max'),
            'ville.required' => __('etablissement.ville.required'),
            'ville.string' => __('etablissement.ville.string'),
            'ville.max' => __('etablissement.ville.max'),
            'code.string' => __('etablissement.code.string'),
            'code.max' => __('etablissement.code.max'),
            'code.unique' => __('etablissement.code.unique'),
        ];
    }
}
