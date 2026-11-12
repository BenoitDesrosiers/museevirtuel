<?php

namespace App\Http\Requests\Projet;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valide la création ou la modification d'une annotation inline de l'enseignant.
 */
class UpsertProjetAnnotationRequest extends FormRequest
{
    /** Pattern regex validant les noms de champs annotables (développement_{id}, section_{id}, section_paragraphe_{id} ou renvoi_{id}). */
    public const CHAMP_ANNOTABLE_REGEX = '/^(developpement_\d+|section_\d+|section_paragraphe_\d+|renvoi_\d+|page_titre_contenu|table_matieres_contenu)$/';

    /**
     * Détermine si l'utilisateur est autorisé à faire cette requête.
     * L'autorisation reste vérifiée dans le contrôleur (sera centralisée dans une Policy).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Retourne les règles de validation d'une annotation inline.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'champ' => ['required', 'string', 'regex:'.self::CHAMP_ANNOTABLE_REGEX],
            'commentaire_id' => ['required', 'string', 'max:36'],
            'contenu' => ['required', 'string', 'max:1000'],
            'html' => ['required', 'string'],
            'annotation_type' => ['required', 'string', 'in:commentaire,correction'],
            'points_malus' => ['nullable', 'numeric', 'min:0', 'max:99.99'],
            'cible_user_id' => ['nullable', 'integer', 'exists:users,id'],
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
            'champ.required' => __('projet_annotation.champ.required'),
            'champ.string' => __('projet_annotation.champ.string'),
            'champ.regex' => __('projet_annotation.champ.regex'),
            'commentaire_id.required' => __('projet_annotation.commentaire_id.required'),
            'commentaire_id.string' => __('projet_annotation.commentaire_id.string'),
            'commentaire_id.max' => __('projet_annotation.commentaire_id.max'),
            'contenu.required' => __('projet_annotation.contenu.required'),
            'contenu.string' => __('projet_annotation.contenu.string'),
            'contenu.max' => __('projet_annotation.contenu.max'),
            'html.required' => __('projet_annotation.html.required'),
            'html.string' => __('projet_annotation.html.string'),
            'annotation_type.required' => __('projet_annotation.annotation_type.required'),
            'annotation_type.string' => __('projet_annotation.annotation_type.string'),
            'annotation_type.in' => __('projet_annotation.annotation_type.in'),
            'points_malus.numeric' => __('projet_annotation.points_malus.numeric'),
            'points_malus.min' => __('projet_annotation.points_malus.min'),
            'points_malus.max' => __('projet_annotation.points_malus.max'),
            'cible_user_id.integer' => __('projet_annotation.cible_user_id.integer'),
            'cible_user_id.exists' => __('projet_annotation.cible_user_id.exists'),
        ];
    }
}
