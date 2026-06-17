<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasCritereRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCritereRequest extends FormRequest
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
     * Retourne les règles de validation pour la mise à jour d'un critère.
     *
     * La section d'un critère ne peut pas être changée après sa création ;
     * section_id et type_projet_id sont donc absents de cette requête.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->critereRules();
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
