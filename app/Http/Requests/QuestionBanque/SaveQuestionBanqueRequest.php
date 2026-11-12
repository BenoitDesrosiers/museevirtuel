<?php

namespace App\Http\Requests\QuestionBanque;

use App\Http\Requests\Concerns\AutoriseGestionTypeProjet;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation de la création et de la mise à jour d'une question de la banque d'une section.
 */
class SaveQuestionBanqueRequest extends FormRequest
{
    use AutoriseGestionTypeProjet;

    /**
     * Seul l'enseignant du cours (ou un admin) peut créer ou modifier une question de la banque.
     */
    public function authorize(): bool
    {
        if (! $this->peutGererTypeProjet()) {
            return false;
        }

        $section = $this->route('section');
        abort_if($section->type_projet_id !== $this->route('typeProjet')->id, 404);

        $question = $this->route('question');
        abort_if($question !== null && $question->section_id !== $section->id, 404);

        return true;
    }

    /**
     * Retourne les règles de validation pour la création ou la mise à jour d'une question.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'contenu' => ['required', 'string', 'max:2000'],
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
            'contenu.required' => __('question_banque.contenu.required'),
            'contenu.string' => __('question_banque.contenu.string'),
            'contenu.max' => __('question_banque.contenu.max'),
        ];
    }
}
