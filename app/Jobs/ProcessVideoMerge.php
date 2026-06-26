<?php

namespace App\Jobs;

use App\Actions\ConcateneSegments;
use App\Models\GroupeVideo;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;
use FFMpeg\Format\Video\X264;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ProcessVideoMerge implements ShouldQueue
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
     * @param  GroupeVideo  $videoBase  La vidéo de base à modifier (remplacée par le résultat).
     * @param  int  $videoInsertId  ID de la vidéo à insérer (int pour sérialisation sûre).
     * @param  float  $position  Position en secondes où insérer la vidéo (0 = début).
     */
    public function __construct(
        public GroupeVideo $videoBase,
        public int $videoInsertId,
        public float $position,
    ) {}

    /**
     * Exécute le job de jumelage vidéo par insertion.
     *
     * Stratégie :
     * - Si position == 0          : [insert complet] + [base complet]
     * - Si 0 < position < durée   : [base[0→pos]] + [insert complet] + [base[pos→fin]]
     * - Si position >= durée      : [base complet] + [insert complet]
     *
     * Chaque segment est extrait en MP4/X264, puis concaténé via le concat demuxer.
     */
    public function handle(): void
    {
        $this->videoBase->update(['traitement_statut' => GroupeVideo::TRAITEMENT_EN_COURS]);

        try {
            $videoInsert = GroupeVideo::findOrFail($this->videoInsertId);

            $sourcePath = $this->videoBase->absolutePath();
            $insertPath = $videoInsert->absolutePath();
            $dir = storage_path('app/private/'.dirname($this->videoBase->file_path));

            $ffmpeg = FFMpeg::create([
                'ffmpeg.binaries' => config('laravel-ffmpeg.ffmpeg.binaries', 'ffmpeg'),
                'ffprobe.binaries' => config('laravel-ffmpeg.ffprobe.binaries', 'ffprobe'),
                'timeout' => config('laravel-ffmpeg.timeout', 3600),
            ]);

            $ffprobe = FFProbe::create([
                'ffprobe.binaries' => config('laravel-ffmpeg.ffprobe.binaries', 'ffprobe'),
            ]);

            $dureeBase = (float) $ffprobe->streams($sourcePath)->videos()->first()->get('duration');
            $dureeInsert = (float) $ffprobe->streams($insertPath)->videos()->first()->get('duration');

            // On borne la position à [0, duréeBase] pour éviter des segments négatifs.
            $position = max(0.0, min($this->position, $dureeBase));

            // Construction de la liste ordonnée des segments à extraire.
            // Chaque entrée : [fichierSource, debut, fin]
            $segments = [];

            if ($position > 0) {
                $segments[] = [$sourcePath, 0.0, $position];
            }

            // La vidéo insérée est toujours incluse dans son intégralité.
            $segments[] = [$insertPath, 0.0, $dureeInsert];

            if ($position < $dureeBase) {
                $segments[] = [$sourcePath, $position, $dureeBase];
            }

            // Extraction de chaque segment dans un fichier temporaire.
            $tempFiles = [];
            foreach ($segments as $i => [$src, $debut, $fin]) {
                $tempPath = "{$dir}/_tmp_merge_{$i}_".Str::uuid().'.mp4';
                $this->extraireSegment($ffmpeg, $src, $debut, $fin, $tempPath);
                $tempFiles[] = $tempPath;
            }

            $outputName = Str::uuid().'.mp4';
            $outputPath = "{$dir}/{$outputName}";
            $outputFilePath = dirname($this->videoBase->file_path)."/{$outputName}";

            try {
                (new ConcateneSegments)->execute($tempFiles, $outputPath);
            } finally {
                // Nettoyage garanti même si la concaténation échoue.
                foreach ($tempFiles as $tmp) {
                    @unlink($tmp);
                }
            }

            $nouvelleduree = (int) round(
                (float) $ffprobe->streams($outputPath)->videos()->first()->get('duration')
            );

            // Miniature au quart de la durée finale — sauvegardée dans public/ car non sensible.
            $thumbName = Str::uuid().'.jpg';
            $thumbDir = public_path("thumbnails/groupes/{$this->videoBase->groupe_id}");
            if (! is_dir($thumbDir)) {
                mkdir($thumbDir, 0755, true);
            }
            $thumbPath = "{$thumbDir}/{$thumbName}";
            $thumbFilePath = "thumbnails/groupes/{$this->videoBase->groupe_id}/{$thumbName}";

            $ffVideo = $ffmpeg->open($outputPath);
            $frame = $ffVideo->frame(TimeCode::fromSeconds(max(1, intdiv($nouvelleduree, 4))));
            $frame->save($thumbPath);

            // Supprime l'ancien fichier source et met à jour le modèle.
            $ancienChemin = $this->videoBase->absolutePath();
            if (file_exists($ancienChemin)) {
                @unlink($ancienChemin);
            }

            // La vidéo fusionnée est un nouveau contenu — réinitialise
            // la transcription et en lance une nouvelle sur le fichier produit.
            $this->videoBase->update([
                'file_path' => $outputFilePath,
                'taille' => filesize($outputPath),
                'duree' => $nouvelleduree,
                'thumbnail_path' => $thumbFilePath,
                'traitement_statut' => GroupeVideo::TRAITEMENT_TERMINE,
                'transcription' => null,
                'transcription_segments' => null,
                'transcription_statut' => GroupeVideo::TRANSCRIPTION_EN_ATTENTE,
            ]);

            TranscrireVideo::dispatch($this->videoBase->fresh());

            Log::info('ProcessVideoMerge terminé', ['video_base_id' => $this->videoBase->id, 'video_insert_id' => $this->videoInsertId, 'duree' => $nouvelleduree]);
        } catch (Throwable $e) {
            Log::error('ProcessVideoMerge échoué', [
                'video_base_id' => $this->videoBase->id,
                'video_insert_id' => $this->videoInsertId,
                'message' => $e->getMessage(),
            ]);

            $this->videoBase->update(['traitement_statut' => GroupeVideo::TRAITEMENT_ERREUR]);

            throw $e;
        }
    }

    /**
     * Extrait un segment d'une vidéo source entre deux positions et le sauvegarde.
     *
     * @param  string  $source  Chemin absolu du fichier source.
     * @param  float  $debut  Début du segment en secondes.
     * @param  float  $fin  Fin du segment en secondes.
     * @param  string  $dest  Chemin absolu du fichier de destination.
     */
    private function extraireSegment(FFMpeg $ffmpeg, string $source, float $debut, float $fin, string $dest): void
    {
        $ffVideo = $ffmpeg->open($source);
        $ffVideo->filters()->clip(
            TimeCode::fromSeconds($debut),
            TimeCode::fromSeconds($fin - $debut),
        );
        $ffVideo->save(new X264, $dest);
    }
}
