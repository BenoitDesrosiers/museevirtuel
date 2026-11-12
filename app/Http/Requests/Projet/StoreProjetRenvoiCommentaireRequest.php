<?php

namespace App\Http\Requests\Projet;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valide l'ajout d'un commentaire de l'enseignant sur un renvoi.
 */
class StoreProjetRenvoiCommentaireRequest extends FormRequest
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
     * Retourne les règles de validation du commentaire sur un renvoi.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'contenu' => ['required', 'string', 'max:2000'],
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
            'contenu.required' => __('projet_renvoi_commentaire.contenu.required'),
            'contenu.string' => __('projet_renvoi_commentaire.contenu.string'),
            'contenu.max' => __('projet_renvoi_commentaire.contenu.max'),
        ];
    }
}
