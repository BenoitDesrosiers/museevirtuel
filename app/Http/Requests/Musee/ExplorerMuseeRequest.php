<?php

namespace App\Http\Requests\Musee;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valide les paramètres de filtrage de l'explorateur de musées virtuels.
 *
 * Les trois filtres sont facultatifs et acceptent plusieurs valeurs (multi-sélection).
 * La validation garantit que chaque identifiant est un entier positif,
 * éliminant le besoin de cast manuel côté contrôleur.
 */
class ExplorerMuseeRequest extends FormRequest
{
    /**
     * La page publique de l'explorateur est accessible sans authentification.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'epoque_ids' => ['sometimes', 'array'],
            'epoque_ids.*' => ['integer', 'min:1'],
            'region_ids' => ['sometimes', 'array'],
            'region_ids.*' => ['integer', 'min:1'],
            'thematique_ids' => ['sometimes', 'array'],
            'thematique_ids.*' => ['integer', 'min:1'],
        ];
    }
}
