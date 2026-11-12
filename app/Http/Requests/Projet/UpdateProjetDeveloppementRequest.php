<?php

namespace App\Http\Requests\Projet;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valide la modification d'un paragraphe de développement.
 */
class UpdateProjetDeveloppementRequest extends FormRequest
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
     * Retourne les règles de validation d'un paragraphe de développement.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'titre' => ['nullable', 'string', 'max:500'],
            'contenu' => ['nullable', 'string'],
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
            'titre.string' => __('projet_developpement.titre.string'),
            'titre.max' => __('projet_developpement.titre.max'),
            'contenu.string' => __('projet_developpement.contenu.string'),
        ];
    }
}
