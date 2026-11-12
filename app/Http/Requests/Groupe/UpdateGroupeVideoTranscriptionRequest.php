<?php

namespace App\Http\Requests\Groupe;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valide les segments de transcription d'une vidéo édités manuellement.
 */
class UpdateGroupeVideoTranscriptionRequest extends FormRequest
{
    /**
     * L'autorisation reste vérifiée dans le contrôleur.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Retourne les règles de validation des segments de transcription.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'segments' => ['required', 'array', 'min:1', 'max:500'],
            'segments.*.start' => ['required', 'numeric', 'min:0'],
            'segments.*.end' => ['required', 'numeric', 'gt:segments.*.start'],
            'segments.*.text' => ['required', 'string', 'max:2000'],
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
            'segments.required' => __('groupe_video_transcription.segments.required'),
            'segments.array' => __('groupe_video_transcription.segments.array'),
            'segments.min' => __('groupe_video_transcription.segments.min'),
            'segments.max' => __('groupe_video_transcription.segments.max'),
            'segments.*.start.required' => __('groupe_video_transcription.segments.start.required'),
            'segments.*.start.numeric' => __('groupe_video_transcription.segments.start.numeric'),
            'segments.*.start.min' => __('groupe_video_transcription.segments.start.min'),
            'segments.*.end.required' => __('groupe_video_transcription.segments.end.required'),
            'segments.*.end.numeric' => __('groupe_video_transcription.segments.end.numeric'),
            'segments.*.end.gt' => __('groupe_video_transcription.segments.end.gt'),
            'segments.*.text.required' => __('groupe_video_transcription.segments.text.required'),
            'segments.*.text.string' => __('groupe_video_transcription.segments.text.string'),
            'segments.*.text.max' => __('groupe_video_transcription.segments.text.max'),
        ];
    }
}
