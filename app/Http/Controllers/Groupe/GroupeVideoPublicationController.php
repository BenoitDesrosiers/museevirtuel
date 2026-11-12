<?php

namespace App\Http\Controllers\Groupe;

use App\Concerns\ValidatesGroupeChain;
use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\GroupeVideo;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;

/**
 * Publication d'une vidéo de groupe.
 */
class GroupeVideoPublicationController extends Controller
{
    use ValidatesGroupeChain;

    /**
     * Passe la vidéo du statut brouillon à publié.
     *
     * @throws AuthorizationException
     */
    public function __invoke(Cours $cours, Classe $classe, Groupe $groupe, GroupeVideo $video): RedirectResponse
    {
        $this->verifierChaine($cours, $classe, $groupe, $video);
        $video->load('groupe.classe.cours');
        $this->authorize('publier', $video);

        $video->update(['statut' => 'publié']);

        return back()->with('success', __('video.published'));
    }
}
