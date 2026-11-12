<?php

namespace App\Http\Requests\Cours;

use App\Enums\SessionCours;
use App\Models\Cours;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransfererCoursRequest extends FormRequest
{
    /**
     * Détermine si l'utilisateur est autorisé à transférer ce cours.
     * Délègue à CoursPolicy::update().
     */
    public function authorize(): bool
    {
        /** @var Cours $cours */
        $cours = $this->route('cours');

        return $this->user()->can('update', $cours);
    }

    /**
     * Retourne les règles de validation pour la destination du transfert.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'annee' => ['required', 'integer', 'min:2000', 'max:2100'],
            'session' => ['required', Rule::enum(SessionCours::class)],
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
            'annee.required' => __('cours.annee.required'),
            'annee.integer' => __('cours.annee.integer'),
            'annee.min' => __('cours.annee.min'),
            'annee.max' => __('cours.annee.max'),
            'session.required' => __('cours.session.required'),
            'session.enum' => __('cours.session.enum'),
        ];
    }
}
