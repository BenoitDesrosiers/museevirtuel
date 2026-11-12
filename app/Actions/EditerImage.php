<?php

namespace App\Actions;

use Intervention\Image\Direction;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Action qui permet d'appliquer les différentes transformations de l'image.
 */
class EditerImage
{
    /**
     * Applique une transformation (rogner, pivoter, retourner) à une image et l'écrase sur disque.
     *
     * Le chemin reste le même, donc l'URL publique de l'image ne change pas.
     *
     * @param  string  $chemin  Chemin absolu du fichier image.
     * @param  array{operation: string, x?: int|string|null, y?: int|string|null, width?: int|string|null, height?: int|string|null, angle?: int|string|null, direction?: string|null}  $edition
     * @return int La nouvelle taille du fichier en octets.
     */
    public function execute(string $chemin, array $edition): int
    {
        $image = (new ImageManager(new Driver))->decode($chemin);

        match ($edition['operation']) {
            'crop' => $image->crop(
                (int) $edition['width'],
                (int) $edition['height'],
                (int) $edition['x'],
                (int) $edition['y'],
            ),
            'rotate' => $image->rotate((float) $edition['angle']),
            'flip' => $image->flip(
                $edition['direction'] === 'vertical'
                    ? Direction::VERTICAL
                    : Direction::HORIZONTAL
            ),
        };

        // Encoder à 90 % de qualité pour JPEG/WEBP, sans perte pour PNG.
        $image->save($chemin, quality: 90);

        // La re-compression change le poids du fichier.
        clearstatcache(true, $chemin);

        return filesize($chemin);
    }
}
