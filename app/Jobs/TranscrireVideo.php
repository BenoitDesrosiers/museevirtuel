<?php

namespace App\Jobs;

use App\Models\GroupeVideo;
use FFMpeg\FFMpeg;
use FFMpeg\Format\Audio\Mp3;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class TranscrireVideo implements ShouldQueue
{
    use Queueable;

    /**
     * Nombre maximal de tentatives avant abandon.
     */
    public int $tries = 3;

    /**
     * Timeout d'exécution en secondes (15 minutes).
     *
     * La transcription locale via Whisper peut prendre plusieurs minutes
     * selon la durée de la vidéo et le modèle choisi.
     */
    public int $timeout = 900;

    /**
     * @param  GroupeVideo  $video  La vidéo à transcrire.
     */
    public function __construct(
        public GroupeVideo $video,
    ) {}

    /**
     * Extrait l'audio de la vidéo en MP3 et le transcrit via Whisper local (Python CLI).
     *
     * Stratégie :
     * - Extraction audio en MP3 mono 32 kbps via FFmpeg (4× plus léger que la vidéo source).
     * - Transcription locale avec `whisper` CLI — aucun appel réseau, aucune limite.
     * - Parsing du JSON produit par Whisper pour extraire texte + segments horodatés.
     * - Nettoyage garanti des fichiers temporaires dans le bloc finally.
     *
     * @throws RuntimeException Si la CLI Whisper est absente ou si la transcription échoue.
     */
    public function handle(): void
    {
        Log::info('TranscrireVideo: démarrage', ['video_id' => $this->video->id]);

        $this->video->update(['transcription_statut' => GroupeVideo::TRANSCRIPTION_EN_COURS]);

        $tempAudio = null;
        $outputJson = null;

        try {
            // Vérification préventive du binaire Whisper — si le chemin est mal configuré,
            // on échoue immédiatement sans consommer les tentatives pour un problème permanent.
            $binary = config('services.whisper.binary', 'whisper');
            $check = new Process([$binary, '--version']);
            $check->setTimeout(10);
            $check->run();

            if (! $check->isSuccessful()) {
                $this->video->update(['transcription_statut' => GroupeVideo::TRANSCRIPTION_ERREUR]);
                $this->fail(new RuntimeException(
                    "Whisper CLI introuvable : {$binary}. Vérifiez WHISPER_BINARY dans .env ou lancez `php artisan whisper:check`."
                ));

                return;
            }

            $sourcePath = $this->video->absolutePath();

            Log::info('TranscrireVideo: fichier source', [
                'video_id' => $this->video->id,
                'path' => $sourcePath,
                'existe' => file_exists($sourcePath),
                'taille_mo' => file_exists($sourcePath) ? round(filesize($sourcePath) / 1024 / 1024, 2) : null,
            ]);

            $ffmpeg = FFMpeg::create([
                'ffmpeg.binaries' => config('laravel-ffmpeg.ffmpeg.binaries', 'ffmpeg'),
                'ffprobe.binaries' => config('laravel-ffmpeg.ffprobe.binaries', 'ffprobe'),
                'timeout' => config('laravel-ffmpeg.timeout', 3600),
            ]);

            $tempAudio = sys_get_temp_dir().DIRECTORY_SEPARATOR.'audio_'.Str::uuid().'.mp3';

            Log::info('TranscrireVideo: extraction audio FFmpeg démarrée', [
                'video_id' => $this->video->id,
                'temp_path' => $tempAudio,
            ]);

            // 32 kbps mono est largement suffisant pour Whisper (entraîné en 16 kHz).
            $format = (new Mp3)
                ->setAudioKiloBitrate(32)
                ->setAudioChannels(1);

            $ffmpeg->open($sourcePath)->save($format, $tempAudio);

            Log::info('TranscrireVideo: audio extrait', [
                'video_id' => $this->video->id,
                'taille_mo' => round(filesize($tempAudio) / 1024 / 1024, 2),
            ]);

            $outputDir = sys_get_temp_dir();
            // Whisper nomme le fichier de sortie d'après le fichier d'entrée (sans extension).
            $outputJson = $outputDir.DIRECTORY_SEPARATOR.pathinfo($tempAudio, PATHINFO_FILENAME).'.json';

            $model = config('services.whisper.model', 'small');

            Log::info('TranscrireVideo: transcription Whisper locale démarrée', [
                'video_id' => $this->video->id,
                'model' => $model,
            ]);

            $process = new Process([
                $binary,
                $tempAudio,
                '--model', $model,
                '--output_format', 'json',
                '--output_dir', $outputDir,
            ]);
            $process->setTimeout(900);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException(
                    'Whisper CLI a échoué : '.$process->getErrorOutput()
                );
            }

            if (! file_exists($outputJson)) {
                throw new RuntimeException(
                    "Whisper n'a produit aucun fichier JSON : {$outputJson}"
                );
            }

            $data = json_decode(file_get_contents($outputJson), true);

            // On ne conserve que start/end/text — les autres champs (tokens, logprob, etc.)
            // sont volumineux et inutiles pour le frontend.
            $segments = collect($data['segments'])
                ->map(fn ($s) => [
                    'start' => $s['start'],
                    'end' => $s['end'],
                    'text' => trim($s['text']),
                ])
                ->values()
                ->all();

            $this->video->update([
                'transcription' => $data['text'],
                'transcription_segments' => $segments,
                'transcription_statut' => GroupeVideo::TRANSCRIPTION_TERMINEE,
            ]);

            Log::info('TranscrireVideo: terminé', [
                'video_id' => $this->video->id,
                'nb_segments' => count($segments),
                'longueur_texte' => strlen($data['text']),
            ]);
        } catch (Throwable $e) {
            Log::error('TranscrireVideo échoué', [
                'video_id' => $this->video->id,
                'message' => $e->getMessage(),
            ]);

            $this->video->update([
                'transcription_statut' => GroupeVideo::TRANSCRIPTION_ERREUR,
                'transcription_segments' => null,
            ]);

            throw $e;
        } finally {
            if ($tempAudio && file_exists($tempAudio)) {
                @unlink($tempAudio);
            }

            if ($outputJson && file_exists($outputJson)) {
                @unlink($outputJson);
            }
        }
    }
}
