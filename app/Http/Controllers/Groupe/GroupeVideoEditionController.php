<?php

namespace App\Http\Controllers\Groupe;

use App\Concerns\ValidatesGroupeChain;
use App\Http\Controllers\Controller;
use App\Http\Requests\Groupe\EditerGroupeVideoRequest;
use App\Http\Requests\Groupe\JumelerGroupeVideoRequest;
use App\Jobs\ProcessVideoEdit;
use App\Jobs\ProcessVideoMerge;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\GroupeVideo;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;

/**
 * Montage d'une vidéo de groupe (coupes et jumelage) via FFmpeg.
 */
class GroupeVideoEditionController extends Controller
{
    use ValidatesGroupeChain;

    /**
     * Dispatche un Job de traitement FFmpeg pour appliquer les coupes soumises.
     *
     * Données validées par EditerGroupeVideoRequest.
     *
     * @throws AuthorizationException
     */
    public function editer(EditerGroupeVideoRequest $request, Cours $cours, Classe $classe, Groupe $groupe, GroupeVideo $video): RedirectResponse
    {
        $this->verifierChaine($cours, $classe, $groupe, $video);
        $video->load('groupe.classe.cours');
        $this->authorize('editer', $video);

        $validated = $request->validated();

        // Le statut est mis à jour avant le dispatch pour éviter une fenêtre
        // où le job commence à s'exécuter avant que la DB reflète l'état en_attente.
        $video->update(['traitement_statut' => GroupeVideo::TRAITEMENT_EN_ATTENTE]);

        ProcessVideoEdit::dispatchSync(
            $video,
            (float) $validated['debut'],
            (float) $validated['fin'],
            $validated['coupes'] ?? [],
        );

        return back()->with('success', __('video.processing'));
    }

    /**
     * Dispatche un Job FFmpeg pour insérer une autre vidéo à une position donnée.
     *
     * La vidéo insérée est extraite en entier, puis le résultat remplace la vidéo de base.
     *
     * Données validées par JumelerGroupeVideoRequest.
     *
     * @throws AuthorizationException
     */
    public function jumeler(JumelerGroupeVideoRequest $request, Cours $cours, Classe $classe, Groupe $groupe, GroupeVideo $video): RedirectResponse
    {
        $this->verifierChaine($cours, $classe, $groupe, $video);
        $video->load('groupe.classe.cours');
        $this->authorize('jumeler', $video);

        $validated = $request->validated();

        $videoInsert = GroupeVideo::findOrFail($validated['video_a_inserer_id']);

        // La vidéo à insérer doit appartenir au même groupe.
        abort_if($videoInsert->groupe_id !== $groupe->id, 422);

        // On ne peut pas jumeler une vidéo avec elle-même.
        abort_if($videoInsert->id === $video->id, 422);

        // On ne peut pas utiliser une vidéo déjà en cours de traitement.
        abort_if($videoInsert->isBeingProcessed(), 422);

        $video->update(['traitement_statut' => GroupeVideo::TRAITEMENT_EN_ATTENTE]);
        ProcessVideoMerge::dispatchSync($video, $videoInsert->id, (float) $validated['position']);

        return back()->with('success', __('video.merged'));
    }
}
