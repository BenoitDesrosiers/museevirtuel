<?php

namespace App\Http\Requests\Etudiant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation de l'ajout manuel d'une référence bibliographique par l'étudiant.
 */
class StoreEtudiantReferenceRequest extends FormRequest
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
     * Retourne les règles de validation pour l'ajout d'une référence personnelle.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'titre' => ['required', 'string', 'max:500'],
            'url' => ['nullable', 'url', 'max:500'],
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
            'titre.required' => __('etudiant_reference.titre.required'),
            'titre.string' => __('etudiant_reference.titre.string'),
            'titre.max' => __('etudiant_reference.titre.max'),
            'url.url' => __('etudiant_reference.url.url'),
            'url.max' => __('etudiant_reference.url.max'),
        ];
    }
}
