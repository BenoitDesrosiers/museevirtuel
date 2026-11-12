<?php

namespace App\Http\Requests\Projet;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valide la modification du titre et des contenus manuels (page titre, table des matières) du projet.
 */
class UpdateProjetRechercheRequest extends FormRequest
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
     * Retourne les règles de validation du projet de recherche.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'titre_projet' => ['sometimes', 'nullable', 'string', 'max:500'],
            'page_titre_contenu' => ['sometimes', 'nullable', 'string', 'max:15000'],
            'table_matieres_contenu' => ['sometimes', 'nullable', 'string', 'max:15000'],
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
            'titre_projet.string' => __('projet_recherche.titre_projet.string'),
            'titre_projet.max' => __('projet_recherche.titre_projet.max'),
            'page_titre_contenu.string' => __('projet_recherche.page_titre_contenu.string'),
            'page_titre_contenu.max' => __('projet_recherche.page_titre_contenu.max'),
            'table_matieres_contenu.string' => __('projet_recherche.table_matieres_contenu.string'),
            'table_matieres_contenu.max' => __('projet_recherche.table_matieres_contenu.max'),
        ];
    }
}
