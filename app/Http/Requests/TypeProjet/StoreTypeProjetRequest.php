<?php

namespace App\Http\Requests\TypeProjet;

use App\Enums\TypeSection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation de la création d'un type de projet (avec sections optionnelles).
 */
class StoreTypeProjetRequest extends FormRequest
{
    /**
     * Seul l'enseignant du cours (ou un admin) peut créer un type de projet.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('cours'));
    }

    /**
     * Retourne les règles de validation pour la création d'un type de projet.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:150'],
            'type' => ['required', 'string', 'in:standard,musee'],
            'description' => ['nullable', 'string', 'max:1000'],
            'date_remise' => ['required', 'date_format:Y-m-d\TH:i'],
            'remises_multiples' => ['boolean'],
            'retard_permis' => ['boolean'],
            'generer_page_titre' => ['boolean'],
            'generer_table_matieres' => ['boolean'],
            'aide_reference' => ['boolean'],
            'has_introduction' => ['boolean'],
            'has_conclusion_individuelle' => ['boolean'],
            'ponderation' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_sommatif' => ['boolean'],
            'sections' => ['nullable', 'array'],
            'sections.*.label' => ['required', 'string', 'max:200'],
            'sections.*.description' => ['nullable', 'string', 'max:1000'],
            'sections.*.type' => ['nullable', Rule::enum(TypeSection::class)],
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
            'nom.required' => __('type_projet.nom.required'),
            'nom.string' => __('type_projet.nom.string'),
            'nom.max' => __('type_projet.nom.max'),
            'type.required' => __('type_projet.type.required'),
            'type.string' => __('type_projet.type.string'),
            'type.in' => __('type_projet.type.in'),
            'description.string' => __('type_projet.description.string'),
            'description.max' => __('type_projet.description.max'),
            'date_remise.required' => __('type_projet.date_remise.required'),
            'date_remise.date_format' => __('type_projet.date_remise.date_format'),
            'remises_multiples.boolean' => __('type_projet.remises_multiples.boolean'),
            'retard_permis.boolean' => __('type_projet.retard_permis.boolean'),
            'generer_page_titre.boolean' => __('type_projet.generer_page_titre.boolean'),
            'generer_table_matieres.boolean' => __('type_projet.generer_table_matieres.boolean'),
            'aide_reference.boolean' => __('type_projet.aide_reference.boolean'),
            'has_introduction.boolean' => __('type_projet.has_introduction.boolean'),
            'has_conclusion_individuelle.boolean' => __('type_projet.has_conclusion_individuelle.boolean'),
            'ponderation.numeric' => __('type_projet.ponderation.numeric'),
            'ponderation.min' => __('type_projet.ponderation.min'),
            'ponderation.max' => __('type_projet.ponderation.max'),
            'is_sommatif.boolean' => __('type_projet.is_sommatif.boolean'),
            'sections.array' => __('type_projet.sections.array'),
            'sections.*.label.required' => __('type_projet.sections.label.required'),
            'sections.*.label.string' => __('type_projet.sections.label.string'),
            'sections.*.label.max' => __('type_projet.sections.label.max'),
            'sections.*.description.string' => __('type_projet.sections.description.string'),
            'sections.*.description.max' => __('type_projet.sections.description.max'),
            'sections.*.type.enum' => __('type_projet.sections.type.enum'),
        ];
    }
}
