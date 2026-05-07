<?php

namespace App\Http\Requests;

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
}
