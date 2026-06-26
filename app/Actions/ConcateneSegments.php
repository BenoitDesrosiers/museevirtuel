<?php

namespace App\Actions;

use Illuminate\Support\Str;

/**
 * Concatène des fichiers MP4 via le concat demuxer de FFmpeg.
 *
 * Centralisé ici pour éviter la duplication entre ProcessVideoEdit et
 * ProcessVideoMerge, qui partagent exactement la même logique.
 */
class ConcateneSegments
{
    /**
     * Concatène les fichiers MP4 fournis dans l'ordre et écrit le résultat dans $outputPath.
     *
     * Utilise le concat demuxer FFmpeg (-f concat -safe 0 -c copy) pour assembler
     * sans réencodage. Un fichier liste temporaire est créé puis supprimé.
     *
     * @param  array<string>  $files  Chemins absolus des segments MP4 à assembler.
     * @param  string  $outputPath  Chemin absolu du fichier MP4 de sortie.
     *
     * @throws \RuntimeException si FFmpeg retourne un code de sortie non-zéro.
     */
    public function execute(array $files, string $outputPath): void
    {
        // Str::uuid() offre 128 bits d'entropie cryptographique, contrairement à
        // uniqid() (timestamp microseconde) qui est prévisible sur serveur partagé.
        $listFile = sys_get_temp_dir().'/concat_'.Str::uuid().'.txt';

        // Le format FFmpeg concat list utilise le guillemet simple comme délimiteur.
        // addslashes() ne gère pas correctement les ' dans les chemins Unix :
        // on utilise la séquence d'échappement POSIX '\'' à la place.
        $lines = array_map(fn ($f) => "file '".str_replace("'", "'\\''", $f)."'", $files);
        file_put_contents($listFile, implode("\n", $lines));

        $ffmpegBin = config('laravel-ffmpeg.ffmpeg.binaries', 'ffmpeg');
        $cmd = sprintf(
            '%s -f concat -safe 0 -i %s -c copy %s -y 2>&1',
            escapeshellarg($ffmpegBin),
            escapeshellarg($listFile),
            escapeshellarg($outputPath),
        );

        exec($cmd, $output, $returnCode);
        @unlink($listFile);

        if ($returnCode !== 0) {
            throw new \RuntimeException('Échec de la concaténation FFmpeg : '.implode("\n", $output));
        }
    }
}
