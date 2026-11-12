<?php

namespace App\Http\Controllers\Groupe;

use App\Concerns\ValidatesGroupeChain;
use App\Http\Controllers\Controller;
use App\Http\Requests\Groupe\SaveGroupeVideoChapitreRequest;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\GroupeVideo;
use App\Models\GroupeVideoChapitre;
use App\Services\OrdreElements;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class GroupeVideoChapitreController extends Controller
{
    use ValidatesGroupeChain;

    /**
     * Crée un nouveau chapitre pour la vidéo.
     *
     * L'ordre est calculé automatiquement comme le max existant + 1.
     *
     * Données validées par SaveGroupeVideoChapitreRequest.
     *
     * @throws AuthorizationException
     */
    public function store(
        SaveGroupeVideoChapitreRequest $request,
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        GroupeVideo $video,
        OrdreElements $ordres,
    ): RedirectResponse {
        $this->verifierChaine($cours, $classe, $groupe, $video);
        $video->load('groupe.classe.cours');
        $this->authorize('gererChapitres', $video);

        $validated = $request->validated();

        $ordre = $ordres->prochain($video->chapitres());

        $video->chapitres()->create([
            'label' => $validated['label'],
            'debut' => (float) $validated['debut'],
            'fin' => isset($validated['fin']) ? (float) $validated['fin'] : null,
            'ordre' => $ordre,
        ]);

        return back()->with('success', __('chapitre.created'));
    }

    /**
     * Met à jour le label et les bornes temporelles d'un chapitre existant.
     *
     * Données validées par SaveGroupeVideoChapitreRequest.
     *
     * @throws AuthorizationException
     */
    public function update(
        SaveGroupeVideoChapitreRequest $request,
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        GroupeVideo $video,
        GroupeVideoChapitre $chapitre,
    ): RedirectResponse {
        $this->verifierChaine($cours, $classe, $groupe, $video);
        $video->load('groupe.classe.cours');
        $this->authorize('gererChapitres', $video);

        abort_if($chapitre->video_id !== $video->id, 404);

        $validated = $request->validated();

        $chapitre->update([
            'label' => $validated['label'],
            'debut' => (float) $validated['debut'],
            'fin' => isset($validated['fin']) ? (float) $validated['fin'] : null,
        ]);

        return back()->with('success', __('chapitre.updated'));
    }

    /**
     * Supprime un chapitre de la vidéo.
     *
     * @throws AuthorizationException
     */
    public function destroy(
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        GroupeVideo $video,
        GroupeVideoChapitre $chapitre,
    ): RedirectResponse {
        $this->verifierChaine($cours, $classe, $groupe, $video);
        $video->load('groupe.classe.cours');
        $this->authorize('gererChapitres', $video);

        abort_if($chapitre->video_id !== $video->id, 404);

        $chapitre->delete();

        return back()->with('success', __('chapitre.deleted'));
    }

    /**
     * Retourne les chapitres de la vidéo sous forme JSON (pour le polling).
     *
     * @throws AuthorizationException
     */
    public function index(
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        GroupeVideo $video,
    ): JsonResponse {
        $this->verifierChaine($cours, $classe, $groupe, $video);
        $video->load('groupe.classe.cours');
        $this->authorize('view', $video);

        return response()->json([
            'chapitres' => $video->chapitres()->get(['id', 'label', 'debut', 'fin', 'ordre']),
        ]);
    }
}
