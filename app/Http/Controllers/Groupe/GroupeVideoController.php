<?php

namespace App\Http\Controllers\Groupe;

use App\Actions\StoreUploadedFile;
use App\Concerns\ValidatesGroupeChain;
use App\Http\Controllers\Controller;
use App\Http\Requests\Groupe\StoreGroupeVideoRequest;
use App\Http\Requests\Groupe\UpdateGroupeVideoRequest;
use App\Jobs\TranscrireVideo;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\GroupeVideo;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class GroupeVideoController extends Controller
{
    use ValidatesGroupeChain;

    /**
     * Affiche la liste des vidéos du groupe.
     *
     * @throws AuthorizationException
     */
    public function index(Cours $cours, Classe $classe, Groupe $groupe): Response
    {
        $this->verifierChaine($cours, $classe, $groupe);
        $groupe->load('classe.cours');
        $this->authorize('view', $groupe);

        $user = auth()->user();
        $estEnseignant = $user->isEnseignant()
            && $cours->enseignant_id === $user->id;

        $videos = $groupe->videos()
            ->visiblesPour($user, $estEnseignant)
            ->with('auteur:id,prenom,nom')
            ->get();

        return Inertia::render('Groupe/Videos/Index', [
            'cours' => $cours,
            'classe' => $classe,
            'groupe' => $groupe,
            'videos' => $videos,
        ]);
    }

    /**
     * Uploade une vidéo et l'associe au groupe en statut brouillon.
     *
     * Limite : 500 Mo. Formats : mp4, webm, mov, avi, mkv.
     *
     * Données validées par StoreGroupeVideoRequest.
     *
     * @throws AuthorizationException
     */
    public function store(StoreGroupeVideoRequest $request, Cours $cours, Classe $classe,
        Groupe $groupe, StoreUploadedFile $fichiers): RedirectResponse
    {
        $this->verifierChaine($cours, $classe, $groupe);
        $groupe->load('classe.cours');
        $this->authorize('create', [GroupeVideo::class, $groupe]);

        // Stockage privé (hors webroot) : l'accès passe par l'endpoint stream() authentifié.
        $fichier = $fichiers->executePrive($request->file('fichier'), "medias/groupes/{$groupe->id}/videos");

        $video = $groupe->videos()->create([
            ...$fichier,
            'user_id' => auth()->id(),
            'titre' => $request->input('titre'),
            'description' => $request->input('description'),
            'statut' => 'brouillon',
            'transcription_statut' => GroupeVideo::TRANSCRIPTION_EN_ATTENTE,
        ]);

        // Lance la transcription dès l'upload — pas besoin d'attendre
        // un traitement FFmpeg puisque la vidéo n'est pas encore éditée.
        TranscrireVideo::dispatch($video);

        return back()->with('success', __('video.uploaded'));
    }

    /**
     * Affiche une vidéo et son éditeur de timeline.
     *
     * @throws AuthorizationException
     */
    public function show(Cours $cours, Classe $classe, Groupe $groupe, GroupeVideo $video): Response
    {
        $this->verifierChaine($cours, $classe, $groupe, $video);
        $video->load('auteur:id,prenom,nom', 'groupe.classe.cours');
        $this->authorize('view', $video);

        $user = auth()->user();
        $estEnseignant = $user->isEnseignant()
            && $groupe->classe->cours->enseignant_id === $user->id;

        // Seules les vidéos visibles par l'utilisateur courant sont proposées au jumelage.
        // Un étudiant ne doit pas voir les brouillons de ses coéquipiers ici.
        $autresVideos = GroupeVideo::where('groupe_id', $groupe->id)
            ->where('id', '!=', $video->id)
            ->visiblesPour($user, $estEnseignant)
            ->where(function ($q) {
                $q->whereNull('traitement_statut')
                    ->orWhere('traitement_statut', GroupeVideo::TRAITEMENT_TERMINE);
            })
            ->orderByDesc('created_at')
            ->get(['id', 'titre', 'duree', 'thumbnail_path']);

        $video->load('chapitres');

        return Inertia::render('Groupe/Videos/Show', [
            'cours' => $cours,
            'classe' => $classe,
            'groupe' => $groupe,
            'video' => $video,
            'autresVideos' => $autresVideos,
            'peutTranscrire' => auth()->user()->can('transcrire', $video),
            'peutModifierTranscription' => auth()->user()->can('modifierTranscription', $video),
            'peutGererChapitres' => auth()->user()->can('gererChapitres', $video),
        ]);
    }

    /**
     * Met à jour le titre et la description d'une vidéo.
     *
     * Données validées par UpdateGroupeVideoRequest.
     *
     * @throws AuthorizationException
     */
    public function update(UpdateGroupeVideoRequest $request, Cours $cours, Classe $classe, Groupe $groupe, GroupeVideo $video): RedirectResponse
    {
        $this->verifierChaine($cours, $classe, $groupe, $video);
        $video->load('groupe.classe.cours');
        $this->authorize('update', $video);

        $video->update($request->only('titre', 'description'));

        return back()->with('success', __('video.updated'));
    }

    /**
     * Supprime la vidéo et son fichier physique.
     *
     * @throws AuthorizationException
     */
    public function destroy(Cours $cours, Classe $classe, Groupe $groupe, GroupeVideo $video): RedirectResponse
    {
        $this->verifierChaine($cours, $classe, $groupe, $video);
        $video->load('groupe.classe.cours');
        $this->authorize('delete', $video);

        $video->deleteWithFileAndThumbnail();

        return back()->with('success', __('video.deleted'));
    }

    /**
     * Retourne le statut de traitement actuel (endpoint de polling).
     *
     * Inclut aussi transcription_statut et transcription pour éviter
     * un second endpoint dédié.
     *
     * @throws AuthorizationException
     */
    public function statut(Cours $cours, Classe $classe, Groupe $groupe, GroupeVideo $video): JsonResponse
    {
        $this->verifierChaine($cours, $classe, $groupe, $video);
        $video->load('groupe.classe.cours');
        $this->authorize('view', $video);

        return response()->json([
            'traitement_statut' => $video->traitement_statut,
            'duree' => $video->duree,
            'thumbnail_url' => $video->thumbnail_url,
            'transcription_statut' => $video->transcription_statut,
            'transcription' => $video->transcription,
            'transcription_segments' => $video->transcription_segments,
        ]);
    }
}
