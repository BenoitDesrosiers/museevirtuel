<?php

namespace App\Jobs;

use App\Models\GroupeMedia;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;
use RuntimeException;
use Throwable;

class TranscrireGroupeMedia implements ShouldQueue
{
    use Queueable;

    /**
     * Nombre maximal de tentatives avant abandon.
     */
    public int $tries = 2;

    /**
     * Timeout d'exécution en secondes (5 minutes).
     *
     * Les fichiers audio sont déjà légers — pas besoin d'extraction FFmpeg.
     */
    public int $timeout = 300;

    /**
     * @param  GroupeMedia  $media  Le message vocal à transcrire.
     */
    public function __construct(
        public GroupeMedia $media,
    ) {}

    /**
     * Envoie le fichier audio directement à l'API Whisper et sauvegarde la transcription.
     *
     * Contrairement aux vidéos, les messages vocaux sont déjà en format audio
     * (mp3, wav, ogg, m4a, aac), ce qui évite l'étape d'extraction FFmpeg.
     *
     * @throws RuntimeException Si la clé API OpenAI est absente.
     */
    public function handle(): void
    {
        // Vérification préventive de la clé API — échoue proprement plutôt
        // que de laisser l'erreur remonter comme une exception réseau cryptique.
        if (empty(config('openai.api_key'))) {
            $this->media->update(['transcription_statut' => GroupeMedia::TRANSCRIPTION_ERREUR]);

            throw new RuntimeException('OPENAI_API_KEY est absent de la configuration.');
        }

        $this->media->update(['transcription_statut' => GroupeMedia::TRANSCRIPTION_EN_COURS]);

        $handle = null;

        try {
            $filePath = public_path($this->media->file_path);
            $handle = fopen($filePath, 'r');

            $response = OpenAI::audio()->transcribe([
                'model' => 'whisper-1',
                'file' => $handle,
                // Pas de response_format → JSON par défaut, ce qui peuple $response->text.
            ]);

            $this->media->update([
                'transcription' => $response->text,
                'transcription_statut' => GroupeMedia::TRANSCRIPTION_TERMINEE,
            ]);

            Log::info('TranscrireGroupeMedia terminé', ['media_id' => $this->media->id]);
        } catch (Throwable $e) {
            Log::error('TranscrireGroupeMedia échoué', [
                'media_id' => $this->media->id,
                'message' => $e->getMessage(),
            ]);

            $this->media->update(['transcription_statut' => GroupeMedia::TRANSCRIPTION_ERREUR]);

            throw $e;
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
    }
}
