<?php

namespace App\Http\Requests\Cours;

use App\Enums\SessionCours;
use App\Enums\TypeCours;
use App\Models\Cours;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valide la mise à jour d'un cours.
 */
class UpdateCoursRequest extends FormRequest
{
    /**
     * Détermine si l'utilisateur est autorisé à modifier ce cours.
     * Délègue à CoursPolicy::update().
     */
    public function authorize(): bool
    {
        /** @var Cours $cours */
        $cours = $this->route('cours');

        return $this->user()->can('update', $cours);
    }

    /**
     * Retourne les règles de validation pour la mise à jour d'un cours.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'nom_cours' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'code' => ['required', 'string', 'max:20'],
            'groupe' => ['required', 'string', 'max:20'],
            'annee' => ['required', 'integer', 'min:2000', 'max:2100'],
            'session' => ['required', Rule::enum(SessionCours::class)],
            'type_cours' => ['nullable', Rule::enum(TypeCours::class)],
            'taille_equipe_min' => ['nullable', 'integer', 'min:1', 'max:20'],
            'taille_equipe_max' => ['nullable', 'integer', 'min:1', 'max:20', 'gte:taille_equipe_min'],
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
            'nom_cours.required' => __('cours.nom_cours.required'),
            'nom_cours.string' => __('cours.nom_cours.string'),
            'nom_cours.max' => __('cours.nom_cours.max'),
            'description.string' => __('cours.description.string'),
            'code.required' => __('cours.code.required'),
            'code.string' => __('cours.code.string'),
            'code.max' => __('cours.code.max'),
            'groupe.required' => __('cours.groupe.required'),
            'groupe.string' => __('cours.groupe.string'),
            'groupe.max' => __('cours.groupe.max'),
            'annee.required' => __('cours.annee.required'),
            'annee.integer' => __('cours.annee.integer'),
            'annee.min' => __('cours.annee.min'),
            'annee.max' => __('cours.annee.max'),
            'session.required' => __('cours.session.required'),
            'session.enum' => __('cours.session.enum'),
            'type_cours.enum' => __('cours.type_cours.enum'),
            'taille_equipe_min.integer' => __('cours.taille_equipe_min.integer'),
            'taille_equipe_min.min' => __('cours.taille_equipe_min.min'),
            'taille_equipe_min.max' => __('cours.taille_equipe_min.max'),
            'taille_equipe_max.integer' => __('cours.taille_equipe_max.integer'),
            'taille_equipe_max.min' => __('cours.taille_equipe_max.min'),
            'taille_equipe_max.max' => __('cours.taille_equipe_max.max'),
            'taille_equipe_max.gte' => __('cours.taille_equipe_max.gte'),
        ];
    }
}
