<?php

namespace App\Jobs;

use App\Actions\ConcateneSegments;
use App\Jobs\TranscrireVideo;
use App\Models\GroupeVideo;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;
use FFMpeg\Filters\Video\ClipFilter;
use FFMpeg\Format\Video\X264;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ProcessVideoEdit implements ShouldQueue
{
    use Queueable;

    /**
     * Nombre maximal de tentatives avant abandon.
     */
    public int $tries = 2;

    /**
     * Timeout d'exécution en secondes (10 minutes).
     */
    public int $timeout = 600;

    /**
     * @param  GroupeVideo  $video  L'enregistrement à modifier.
     * @param  float  $debut  Début du segment à conserver (secondes).
     * @param  float  $fin  Fin du segment à conserver (secondes, 0 = jusqu'à la fin).
     * @param  array<array{debut: float, fin: float}>  $coupes  Segments internes à supprimer.
     */
    public function __construct(
        public GroupeVideo $video,
        public float $debut = 0,
        public float $fin = 0,
        public array $coupes = [],
    ) {}

    /**
     * Exécute le job de traitement vidéo.
     *
     * Stratégie :
     * - Si aucune coupe interne → trim simple avec ClipFilter.
     * - Si coupes → extraction des sous-clips valides + concat en liste de fichiers.
     * - Génère toujours une miniature au 1/4 de la durée finale.
     */
    public function handle(): void
    {
        $this->video->update(['traitement_statut' => GroupeVideo::TRAITEMENT_EN_COURS]);

        try {
            $sourcePath = $this->video->absolutePath();
            $dir = storage_path('app/private/'.dirname($this->video->file_path));

            $ffmpeg = FFMpeg::create([
                'ffmpeg.binaries' => config('laravel-ffmpeg.ffmpeg.binaries', 'ffmpeg'),
                'ffprobe.binaries' => config('laravel-ffmpeg.ffprobe.binaries', 'ffprobe'),
                'timeout' => config('laravel-ffmpeg.timeout', 3600),
            ]);

            $ffprobe = FFProbe::create([
                'ffprobe.binaries' => config('laravel-ffmpeg.ffprobe.binaries', 'ffprobe'),
            ]);

            // Durée réelle de la source en secondes.
            $dureeTotale = (float) $ffprobe->streams($sourcePath)->videos()->first()->get('duration');

            $finEffective = $this->fin > 0 ? $this->fin : $dureeTotale;

            // Calcule les segments à conserver après suppression des coupes internes.
            $segments = $this->calculerSegments($this->debut, $finEffective, $this->coupes);

            $outputName = Str::uuid().'.mp4';
            $outputPath = "{$dir}/{$outputName}";
            $outputFilePath = dirname($this->video->file_path)."/{$outputName}";

            if (count($segments) === 1) {
                // Trim simple — plus rapide car évite le réencodage avec concat.
                [$segDebut, $segFin] = $segments[0];
                $ffVideo = $ffmpeg->open($sourcePath);
                $ffVideo->filters()->clip(
                    TimeCode::fromSeconds($segDebut),
                    TimeCode::fromSeconds($segFin - $segDebut),
                );
                $ffVideo->save(new X264, $outputPath);
            } else {
                // Plusieurs segments → extraction individuelle puis concaténation.
                $tempFiles = $this->extraireSegments($ffmpeg, $sourcePath, $segments, $dir);
                try {
                    (new ConcateneSegments)->execute($tempFiles, $outputPath);
                } finally {
                    // Nettoyage garanti même si la concaténation échoue.
                    foreach ($tempFiles as $tmp) {
                        @unlink($tmp);
                    }
                }
            }

            // Durée du fichier produit.
            $nouvelleduree = (int) round(
                (float) $ffprobe->streams($outputPath)->videos()->first()->get('duration')
            );

            // Miniature au quart de la durée — sauvegardée dans public/ car non sensible.
            $thumbName = Str::uuid().'.jpg';
            $thumbDir = public_path("thumbnails/groupes/{$this->video->groupe_id}");
            if (! is_dir($thumbDir)) {
                mkdir($thumbDir, 0755, true);
            }
            $thumbPath = "{$thumbDir}/{$thumbName}";
            $thumbFilePath = "thumbnails/groupes/{$this->video->groupe_id}/{$thumbName}";

            $ffVideo = $ffmpeg->open($outputPath);
            $frame = $ffVideo->frame(TimeCode::fromSeconds(max(1, intdiv($nouvelleduree, 4))));
            $frame->save($thumbPath);

            // Supprime l'ancien fichier source et met à jour le modèle.
            $ancienChemin = $this->video->absolutePath();
            if (file_exists($ancienChemin)) {
                @unlink($ancienChemin);
            }

            // La vidéo a changé (trim/coupes) — l'ancienne transcription est invalide.
            // On la réinitialise et on relance la transcription sur le nouveau fichier.
            $this->video->update([
                'file_path' => $outputFilePath,
                'taille' => filesize($outputPath),
                'duree' => $nouvelleduree,
                'thumbnail_path' => $thumbFilePath,
                'traitement_statut' => GroupeVideo::TRAITEMENT_TERMINE,
                'transcription' => null,
                'transcription_segments' => null,
                'transcription_statut' => GroupeVideo::TRANSCRIPTION_EN_ATTENTE,
            ]);

            TranscrireVideo::dispatch($this->video->fresh());

            Log::info('ProcessVideoEdit terminé', ['video_id' => $this->video->id, 'duree' => $nouvelleduree]);
        } catch (Throwable $e) {
            Log::error('ProcessVideoEdit échoué', [
                'video_id' => $this->video->id,
                'message' => $e->getMessage(),
            ]);

            $this->video->update(['traitement_statut' => GroupeVideo::TRAITEMENT_ERREUR]);

            throw $e;
        }
    }

    /**
     * Calcule la liste des segments [debut, fin] à conserver.
     *
     * Exemple : source de 0→60s, coupes [{20,30}, {45,50}]
     * → [[0,20], [30,45], [50,60]]
     *
     * @param  array<array{debut: float, fin: float}>  $coupes
     * @return array<array{0: float, 1: float}>
     */
    private function calculerSegments(float $debut, float $fin, array $coupes): array
    {
        if (empty($coupes)) {
            return [[$debut, $fin]];
        }

        // Trier les coupes par début.
        usort($coupes, fn ($a, $b) => $a['debut'] <=> $b['debut']);

        $segments = [];
        $curseur = $debut;

        foreach ($coupes as $coupe) {
            if ($coupe['debut'] > $curseur) {
                $segments[] = [$curseur, $coupe['debut']];
            }
            $curseur = $coupe['fin'];
        }

        if ($curseur < $fin) {
            $segments[] = [$curseur, $fin];
        }

        return $segments;
    }

    /**
     * Extrait chaque segment dans un fichier temporaire et retourne leurs chemins.
     *
     * @param  array<array{0: float, 1: float}>  $segments
     * @return array<string>
     */
    private function extraireSegments(FFMpeg $ffmpeg, string $sourcePath, array $segments, string $dir): array
    {
        $tempFiles = [];

        foreach ($segments as $i => [$segDebut, $segFin]) {
            $tempPath = "{$dir}/_tmp_segment_{$i}_".Str::uuid().'.mp4';
            $ffVideo = $ffmpeg->open($sourcePath);
            $ffVideo->filters()->clip(
                TimeCode::fromSeconds($segDebut),
                TimeCode::fromSeconds($segFin - $segDebut),
            );
            $ffVideo->save(new X264, $tempPath);
            $tempFiles[] = $tempPath;
        }

        return $tempFiles;
    }
}
