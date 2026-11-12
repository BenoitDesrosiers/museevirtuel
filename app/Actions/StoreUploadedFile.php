<?php

namespace App\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StoreUploadedFile
{
    /**
     * Déplace un fichier uploadé dans le dossier public cible et retourne ses métadonnées.
     *
     * @param  UploadedFile  $file  Le fichier provenant de la requête.
     * @param  string  $directory  Chemin relatif sous public/ (ex: "images/groupes/3").
     * @return array{nom_original: string, file_path: string, taille: int}
     */
    public function execute(UploadedFile $file, string $directory): array
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $fullDir = public_path($directory);

        if (! is_dir($fullDir)) {
            mkdir($fullDir, 0755, true);
        }

        $filename = Str::uuid().'.'.$ext;

        // Capturer la taille AVANT move() : après déplacement, le fichier temp
        // n'existe plus et getSize() lèverait SplFileInfo::getSize(): stat failed.
        $taille = $file->getSize();
        $nomOriginal = $file->getClientOriginalName();

        $file->move($fullDir, $filename);

        return [
            'nom_original' => $nomOriginal,
            'file_path' => "{$directory}/{$filename}",
            'taille' => $taille,
        ];
    }

    /**
     * Enregistre un fichier uploadé hors webroot (disque « local ») et retourne ses métadonnées.
     *
     * Pour les fichiers qui ne doivent être lus qu'à travers une route authentifiée
     * (vidéos de groupe, enregistrements visio).
     *
     * @param  UploadedFile  $file  Le fichier provenant de la requête.
     * @param  string  $directory  Chemin relatif au disque local (ex: "medias/groupes/3/videos").
     * @param  string|null  $nomFichier  Nom sans extension ; un UUID par défaut. Un nom fixe remplace le fichier existant.
     * @return array{nom_original: string, file_path: string, taille: int}
     */
    public function executePrive(UploadedFile $file, string $directory, ?string $nomFichier = null): array
    {
        $filename = ($nomFichier ?? (string) Str::uuid()).'.'.strtolower($file->getClientOriginalExtension());

        Storage::disk('local')->putFileAs($directory, $file, $filename);

        return [
            'nom_original' => $file->getClientOriginalName(),
            'file_path' => "{$directory}/{$filename}",
            'taille' => $file->getSize(),
        ];
    }
}
