<?php

namespace App\Http\Controllers;

use App\Concerns\ValidatesGroupeChain;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\GroupeVideo;
use App\Models\GroupeVideoChapitre;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GroupeVideoChapitreController extends Controller
{
    use ValidatesGroupeChain;

    /**
     * Crée un nouveau chapitre pour la vidéo.
     *
     * L'ordre est calculé automatiquement comme le max existant + 1.
     *
     * @throws AuthorizationException
     */
    public function store(
        Request $request,
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        GroupeVideo $video,
    ): RedirectResponse {
        $this->verifierChaine($cours, $classe, $groupe, $video);
        $video->load('groupe.classe.cours');
        $this->authorize('gererChapitres', $video);

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'debut' => ['required', 'numeric', 'min:0'],
            'fin' => ['nullable', 'numeric', 'gt:debut'],
        ]);

        $ordre = $video->chapitres()->max('ordre') ?? 0;

        $video->chapitres()->create([
            'label' => $validated['label'],
            'debut' => (float) $validated['debut'],
            'fin' => isset($validated['fin']) ? (float) $validated['fin'] : null,
            'ordre' => $ordre + 1,
        ]);

        return back()->with('success', 'Chapitre ajouté.');
    }

    /**
     * Met à jour le label et les bornes temporelles d'un chapitre existant.
     *
     * @throws AuthorizationException
     */
    public function update(
        Request $request,
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        GroupeVideo $video,
        GroupeVideoChapitre $chapitre,
    ): RedirectResponse {
        $this->verifierChaine($cours, $classe, $groupe, $video);
        $video->load('groupe.classe.cours');
        $this->authorize('gererChapitres', $video);

        abort_if($chapitre->video_id !== $video->id, 403);

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'debut' => ['required', 'numeric', 'min:0'],
            'fin' => ['nullable', 'numeric', 'gt:debut'],
        ]);

        $chapitre->update([
            'label' => $validated['label'],
            'debut' => (float) $validated['debut'],
            'fin' => isset($validated['fin']) ? (float) $validated['fin'] : null,
        ]);

        return back()->with('success', 'Chapitre mis à jour.');
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

        abort_if($chapitre->video_id !== $video->id, 403);

        $chapitre->delete();

        return back()->with('success', 'Chapitre supprimé.');
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
