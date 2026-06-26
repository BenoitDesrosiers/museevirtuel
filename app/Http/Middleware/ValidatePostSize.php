<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\ValidatePostSize as BaseValidatePostSize;

/**
 * Surcharge de ValidatePostSize pour les uploads vidéo.
 *
 * La limite réelle est appliquée par PHP-FPM (php.ini : post_max_size = 2G)
 * et par Nginx (client_max_body_size : 2G). Cette classe synchronise le
 * contrôle Laravel avec ces limites pour éviter un faux reject prématuré.
 */
class ValidatePostSize extends BaseValidatePostSize
{
    /**
     * Retourne la limite POST configurée pour cette application (2 Go).
     *
     * PHP-FPM et Nginx appliquent la même limite — on évite ici le
     * double contrôle basé sur ini_get() qui peut retourner l'ancienne
     * valeur si le processus PHP n'a pas encore rechargé son php.ini.
     */
    protected function getPostMaxSize(): int
    {
        return 2 * 1024 * 1024 * 1024; // 2 Go
    }
}
