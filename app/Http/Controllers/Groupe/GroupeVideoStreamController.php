<?php

namespace App\Http\Controllers\Groupe;

use App\Http\Controllers\Controller;
use App\Models\Groupe;
use App\Models\GroupeVideo;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Diffusion du fichier d'une vidéo de groupe.
 */
class GroupeVideoStreamController extends Controller
{
    /**
     * Diffuse le fichier vidéo privé après vérification d'autorisation.
     *
     * Les vidéos sont stockées hors webroot (storage/app/private/).
     * BinaryFileResponse gère les requêtes HTTP Range, indispensables
     * pour la navigation dans la vidéo côté navigateur.
     *
     * @throws AuthorizationException
     */
    public function __invoke(GroupeVideo $video): BinaryFileResponse
    {
        $video->load('groupe.classe.cours');
        $this->authorize('view', $video);

        $path = $video->absolutePath();

        abort_unless(file_exists($path), 404);

        return response()->file($path);
    }
}
