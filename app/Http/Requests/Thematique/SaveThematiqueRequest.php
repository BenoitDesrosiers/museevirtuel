<?php

namespace App\Http\Requests\Thematique;

use App\Models\Thematique;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Valide la création et la mise à jour d'une thématique.
 */
class SaveThematiqueRequest extends FormRequest
{
    /**
     * Détermine si l'utilisateur est autorisé à faire cette requête.
     * En création, le rôle est vérifié par le middleware de route ;
     * en mise à jour, on délègue à ThematiquePolicy::update().
     */
    public function authorize(): bool
    {
        /** @var Thematique|null $thematique */
        $thematique = $this->route('thematique');

        return $thematique === null || $this->user()->can('update', $thematique);
    }

    /**
     * Retourne les règles de validation d'une thématique.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'periode_historique' => ['nullable', 'string', 'max:255'],
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
            'nom.required' => __('thematique.nom.required'),
            'nom.string' => __('thematique.nom.string'),
            'nom.max' => __('thematique.nom.max'),
            'description.string' => __('thematique.description.string'),
            'periode_historique.string' => __('thematique.periode_historique.string'),
            'periode_historique.max' => __('thematique.periode_historique.max'),
        ];
    }
}
