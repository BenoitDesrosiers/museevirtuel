<?php

namespace App\Http\Requests\TypeProjet;

use App\Http\Requests\Concerns\AutoriseGestionTypeProjet;
use App\Http\Requests\Concerns\HasCritereRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreCritereRequest extends FormRequest
{
    use AutoriseGestionTypeProjet, HasCritereRules;

    /**
     * Seul l'enseignant du cours (ou un admin) peut ajouter un critère.
     * L'appartenance de section_id au type de projet reste vérifiée dans le contrôleur.
     */
    public function authorize(): bool
    {
        return $this->peutGererTypeProjet();
    }

    /**
     * Retourne les règles de validation pour la création d'un critère.
     *
     * `section_id` null = critère global (affiché avant les sections).
     * L'appartenance de section_id au TypeProjet est vérifiée dans le controller.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(
            ['section_id' => ['nullable', 'integer']],
            $this->critereRules(),
        );
    }

    /**
     * Retourne les messages d'erreur de validation traduits.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'section_id.integer' => __('critere.section_id.integer'),
            'type.required' => __('critere.type.required'),
            'type.string' => __('critere.type.string'),
            'type.in' => __('critere.type.in'),
            'contenu_type.required' => __('critere.contenu_type.required'),
            'contenu_type.string' => __('critere.contenu_type.string'),
            'contenu_type.in' => __('critere.contenu_type.in'),
            'pointage.required' => __('critere.pointage.required'),
            'pointage.numeric' => __('critere.pointage.numeric'),
            'pointage.min' => __('critere.pointage.min'),
            'pointage.max' => __('critere.pointage.max'),
            'contenu.string' => __('critere.contenu.string'),
            'contenu.max' => __('critere.contenu.max'),
            'echelle.array' => __('critere.echelle.array'),
            'echelle.*.label.required_if' => __('critere.echelle.label.required_if'),
            'echelle.*.label.string' => __('critere.echelle.label.string'),
            'echelle.*.label.max' => __('critere.echelle.label.max'),
            'echelle.*.points.required_if' => __('critere.echelle.points.required_if'),
            'echelle.*.points.numeric' => __('critere.echelle.points.numeric'),
            'echelle.*.points.min' => __('critere.echelle.points.min'),
            'echelle.*.description.string' => __('critere.echelle.description.string'),
            'echelle.*.description.max' => __('critere.echelle.description.max'),
            'visible.boolean' => __('critere.visible.boolean'),
            'note.string' => __('critere.note.string'),
            'note.max' => __('critere.note.max'),
        ];
    }
}
