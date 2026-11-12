<?php

namespace App\Http\Requests\Visio;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation de la création d'une visioconférence.
 */
class StoreVisioConferenceRequest extends FormRequest
{
    /**
     * Détermine si l'utilisateur est autorisé à faire cette requête.
     * L'autorisation reste vérifiée dans le contrôleur.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Retourne les règles de validation pour la création d'une visioconférence.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'titre' => ['required', 'string', 'max:255'],
            'groupe_id' => ['nullable', 'integer', 'exists:groupes,id'],
            'scheduled_at' => ['nullable', 'date'],
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
            'titre.required' => __('visio_conference.titre.required'),
            'titre.string' => __('visio_conference.titre.string'),
            'titre.max' => __('visio_conference.titre.max'),
            'groupe_id.integer' => __('visio_conference.groupe_id.integer'),
            'groupe_id.exists' => __('visio_conference.groupe_id.exists'),
            'scheduled_at.date' => __('visio_conference.scheduled_at.date'),
        ];
    }
}
