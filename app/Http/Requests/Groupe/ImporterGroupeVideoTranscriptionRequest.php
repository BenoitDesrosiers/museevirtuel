<?php

namespace App\Http\Requests\Groupe;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valide l'import d'un fichier de transcription (.txt, .srt, .vtt).
 */
class ImporterGroupeVideoTranscriptionRequest extends FormRequest
{
    /**
     * L'autorisation reste vérifiée dans le contrôleur.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Retourne les règles de validation du fichier de transcription.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'fichier' => ['required', 'file', 'max:2048', 'mimes:txt,srt,vtt'],
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
            'fichier.required' => __('groupe_video_transcription.fichier.required'),
            'fichier.file' => __('groupe_video_transcription.fichier.file'),
            'fichier.max' => __('groupe_video_transcription.fichier.max'),
            'fichier.mimes' => __('groupe_video_transcription.fichier.mimes'),
        ];
    }
}
