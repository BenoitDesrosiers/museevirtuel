<?php

namespace App\Http\Requests\Commun;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valide un réordonnancement : la liste complète des IDs dans l'ordre voulu.
 *
 * L'autorisation dépend de la ressource parente : chaque domaine a sa sous-classe
 * (ex. ReorderCoursObjectifRequest) qui surcharge authorize(). Les IDs hors de la
 * liste concernée sont ignorés par OrdreElements::reordonner().
 */
class ReorderRequest extends FormRequest
{
    /**
     * Utilisée telle quelle, l'autorisation reste vérifiée par le contrôleur appelant.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Règles de validation de la liste ordonnée.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'ordre' => ['required', 'array'],
            'ordre.*' => ['required', 'integer', 'distinct'],
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
            'ordre.required' => __('reorder.ordre.required'),
            'ordre.array' => __('reorder.ordre.array'),
            'ordre.*.required' => __('reorder.ordre.item.required'),
            'ordre.*.integer' => __('reorder.ordre.item.integer'),
            'ordre.*.distinct' => __('reorder.ordre.item.distinct'),
        ];
    }

    /**
     * Retourne les IDs dans l'ordre reçu.
     *
     * @return list<int>
     */
    public function ids(): array
    {
        return array_map('intval', array_values($this->validated('ordre')));
    }
}
