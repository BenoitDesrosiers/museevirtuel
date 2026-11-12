<?php

namespace App\Http\Requests\Visio;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation de la mise à jour d'une visioconférence.
 */
class UpdateVisioConferenceRequest extends FormRequest
{
    /**
     * Seul l'enseignant du cours (ou un admin) peut modifier une visioconférence du cours.
     */
    public function authorize(): bool
    {
        $visio = $this->route('visio');
        abort_if($visio->cours_id !== $this->route('cours')->id, 404);

        return $this->user()->can('update', $visio);
    }

    /**
     * Retourne les règles de validation pour la mise à jour d'une visioconférence.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'titre' => ['sometimes', 'string', 'max:255'],
            'scheduled_at' => ['nullable', 'date'],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date'],
            'recording_url' => ['nullable', 'url', 'max:2048'],
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
            'titre.string' => __('visio_conference.titre.string'),
            'titre.max' => __('visio_conference.titre.max'),
            'scheduled_at.date' => __('visio_conference.scheduled_at.date'),
            'started_at.date' => __('visio_conference.started_at.date'),
            'ended_at.date' => __('visio_conference.ended_at.date'),
            'recording_url.url' => __('visio_conference.recording_url.url'),
            'recording_url.max' => __('visio_conference.recording_url.max'),
        ];
    }
}
