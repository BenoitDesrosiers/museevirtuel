<?php

namespace App\Http\Controllers\Groupe;

use App\Actions\EditerImage;
use App\Actions\StoreUploadedFile;
use App\Concerns\ValidatesGroupeChain;
use App\Http\Controllers\Controller;
use App\Http\Requests\Groupe\EditerGroupeMediaRequest;
use App\Http\Requests\Groupe\StoreGroupeMediaRequest;
use App\Jobs\TranscrireGroupeMedia;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\GroupeMedia;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;

class GroupeMediaController extends Controller
{
    use ValidatesGroupeChain;

    /**
     * Uploade un média et l'associe au groupe.
     *
     * La validation MIME réelle est assurée par Laravel.
     * Seuls les membres du groupe peuvent uploader.
     *
     * Données validées par StoreGroupeMediaRequest.
     *
     * @throws AuthorizationException
     */
    public function store(StoreGroupeMediaRequest $request, Cours $cours, Classe $classe, Groupe $groupe, StoreUploadedFile $fichiers): RedirectResponse
    {
        $this->verifierChaine($cours, $classe, $groupe);
        $groupe->load('classe.cours');
        $this->authorize('addMedia', $groupe);

        $file = $request->file('fichier');
        $ext = strtolower($file->getClientOriginalExtension());

        $type = match (true) {
            in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif']) => 'photo',
            in_array($ext, ['mp3', 'wav', 'ogg', 'm4a', 'aac']) => 'audio',
            default => 'document',
        };

        $meta = $fichiers->execute(
            $file,
            "images/groupes/{$groupe->id}"
        );

        $groupe->medias()->create([
            'user_id' => auth()->id(),
            'type' => $type,
            ...$meta,
        ]);

        return back()->with('success', __('media.added'));
    }

    /**
     * Supprime un média du groupe et son fichier physique.
     *
     * L'auteur du média, l'enseignant du cours et les admins peuvent supprimer.
     *
     * @throws AuthorizationException
     */
    public function destroy(Cours $cours, Classe $classe, Groupe $groupe, GroupeMedia $media): RedirectResponse
    {
        $this->verifierChaine($cours, $classe, $groupe, $media);

        $user = auth()->user();

        // L'auteur peut supprimer son propre média — les autres cas passent par la Policy
        if ($media->user_id !== $user->id) {
            $groupe->load('classe.cours');
            $this->authorize('deleteMedia', $groupe);
        }

        $media->deleteWithFile();

        return back()->with('success', __('media.deleted'));
    }

    /**
     * Applique une transformation (rogner, pivoter, retourner) à une photo existante.
     *
     * L'image source est écrasée sur disque (même chemin → même URL).
     * Les GIFs sont refusés car l'édition détruit l'animation.
     * Accessible à tous les membres du groupe et à l'enseignant du cours.
     *
     * Données validées par EditerGroupeMediaRequest.
     *
     * @throws AuthorizationException
     */
    public function editer(EditerGroupeMediaRequest $request, Cours $cours, Classe $classe, Groupe $groupe, GroupeMedia $media, EditerImage $editerImage): RedirectResponse
    {
        $this->verifierChaine($cours, $classe, $groupe, $media);
        abort_if($media->type !== 'photo', 422);

        $groupe->load('classe.cours');
        $this->authorize('editerMedia', $groupe);

        // Les GIFs animés ne sont pas éditables sans perdre l'animation.
        $ext = strtolower(pathinfo($media->file_path, PATHINFO_EXTENSION));
        abort_if($ext === 'gif', 422, __('media.gif_non_editable'));

        $taille = $editerImage->execute(public_path($media->file_path), $request->validated());

        $media->update(['taille' => $taille]);

        return back()->with('success', __('media.edited'));
    }

    /**
     * Dispatche un Job de transcription Whisper pour un message vocal.
     *
     * Ne redispatche pas si une transcription est déjà en cours ou en attente,
     * pour éviter les doubles soumissions accidentelles.
     *
     * @throws AuthorizationException
     */
    public function transcrire(Cours $cours, Classe $classe, Groupe $groupe, GroupeMedia $media): RedirectResponse
    {
        $this->verifierChaine($cours, $classe, $groupe, $media);
        $groupe->load('classe.cours');
        $this->authorize('transcrireMedia', $groupe);

        // Seuls les fichiers audio peuvent être transcrits.
        abort_if($media->type !== 'audio', 422);

        // On ne redémarre pas une transcription déjà en cours.
        if ($media->isBeingTranscribed()) {
            return back()->with('info', __('media.transcription_already_running'));
        }

        $media->update(['transcription_statut' => GroupeMedia::TRANSCRIPTION_EN_ATTENTE]);
        TranscrireGroupeMedia::dispatch($media);

        return back()->with('success', __('media.transcription_started'));
    }
}
