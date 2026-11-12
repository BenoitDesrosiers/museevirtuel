<?php

namespace App\Http\Requests\QuestionBanque;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation du choix des questions de la banque par un groupe.
 */
class ChoisirQuestionBanqueRequest extends FormRequest
{
    /**
     * Seuls les membres du groupe peuvent choisir les questions, dans une chaîne d'URL cohérente.
     */
    public function authorize(): bool
    {
        $cours = $this->route('cours');
        $classe = $this->route('classe');
        $groupe = $this->route('groupe');
        $typeProjet = $this->route('typeProjet');

        abort_if($classe->cours_id !== $cours->id, 404);
        abort_if($groupe->classe_id !== $classe->id, 404);
        abort_if($typeProjet->cours_id !== $cours->id, 404);
        abort_if($this->route('section')->type_projet_id !== $typeProjet->id, 404);

        return $groupe->membres()->where('user_id', $this->user()->id)->exists();
    }

    /**
     * Retourne les règles de validation pour le choix des questions par un groupe.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'question_ids' => ['present', 'array'],
            'question_ids.*' => ['integer', 'exists:question_banques,id'],
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
            'question_ids.array' => __('question_banque.question_ids.array'),
            'question_ids.*.integer' => __('question_banque.question_ids.integer'),
            'question_ids.*.exists' => __('question_banque.question_ids.exists'),
        ];
    }
}
