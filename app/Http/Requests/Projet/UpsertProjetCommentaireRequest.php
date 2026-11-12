<?php

namespace App\Http\Requests\Projet;

use Illuminate\Foundation\Http\FormRequest;

class UpsertProjetCommentaireRequest extends FormRequest
{
    private const CHAMP_COMMENTAIRE_REGEX = '/^(normes_presentation|introduction_amener|introduction_poser|introduction_diviser|developpement|developpement_\d+|references|ecriture|conclusion_\d+)$/';

    /**
     * Détermine si l'utilisateur est autorisé à faire cette requête.
     * La vérification enseignant est faite dans le controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Retourne les règles de validation pour l'ajout ou la modification d'un commentaire.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // Champs fixes, développement_{id} (paragraphe dynamique) ou conclusion_{user_id}
            'champ' => ['required', 'string', 'max:100', 'regex:'.self::CHAMP_COMMENTAIRE_REGEX],
            'contenu' => ['required', 'string'],
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
            'champ.required' => __('projet_commentaire.champ.required'),
            'champ.string' => __('projet_commentaire.champ.string'),
            'champ.max' => __('projet_commentaire.champ.max'),
            'champ.regex' => __('projet_commentaire.champ.regex'),
            'contenu.required' => __('projet_commentaire.contenu.required'),
            'contenu.string' => __('projet_commentaire.contenu.string'),
        ];
    }
}
