<?php

use App\Actions\EditerImage;

beforeEach(function () {
    if (! extension_loaded('gd')) {
        $this->markTestSkipped("L'extension GD est requise pour éditer des images.");
    }

    // Image PNG 20×10 : la largeur et la hauteur différentes rendent la rotation vérifiable.
    // tempnam() crée un fichier sans extension : on le remplace par le .png du test.
    $base = tempnam(sys_get_temp_dir(), 'img');
    unlink($base);
    $this->chemin = $base.'.png';
    $img = imagecreatetruecolor(20, 10);
    imagepng($img, $this->chemin);
    imagedestroy($img);
});

afterEach(function () {
    if (isset($this->chemin) && file_exists($this->chemin)) {
        unlink($this->chemin);
    }
});

test('rogner réduit l\'image aux dimensions demandées', function () {
    app(EditerImage::class)->execute($this->chemin, ['operation' => 'crop', 'x' => 2, 'y' => 1, 'width' => 5, 'height' => 4]);

    expect(array_slice(getimagesize($this->chemin), 0, 2))->toBe([5, 4]);
});

test('pivoter de 90° inverse largeur et hauteur', function () {
    app(EditerImage::class)->execute($this->chemin, ['operation' => 'rotate', 'angle' => 90]);

    expect(array_slice(getimagesize($this->chemin), 0, 2))->toBe([10, 20]);
});

test('retourner conserve les dimensions et retourne la nouvelle taille du fichier', function () {
    $taille = app(EditerImage::class)->execute($this->chemin, ['operation' => 'flip', 'direction' => 'vertical']);

    clearstatcache(true, $this->chemin);

    expect(array_slice(getimagesize($this->chemin), 0, 2))->toBe([20, 10])
        ->and($taille)->toBe(filesize($this->chemin));
});
