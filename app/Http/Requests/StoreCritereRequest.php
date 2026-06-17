<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasCritereRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreCritereRequest extends FormRequest
{
    use HasCritereRules;

    /**
     * L'autorisation est vérifiée dans le controller (authorize sur le cours).
     */
    public function authorize(): bool
    {
        return true;
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
     * Retourne les messages d'erreur de validation en français.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->critereMessages();
    }
}
