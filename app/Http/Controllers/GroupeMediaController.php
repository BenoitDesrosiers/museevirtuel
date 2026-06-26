<?php

namespace App\Http\Controllers;

use App\Actions\StoreUploadedFile;
use App\Concerns\ValidatesGroupeChain;
use App\Jobs\TranscrireGroupeMedia;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\GroupeMedia;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Intervention\Image\Direction;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class GroupeMediaController extends Controller
{
    use ValidatesGroupeChain;

    /**
     * Uploade un média et l'associe au groupe.
     *
     * La validation MIME réelle est assurée par Laravel.
     * Seuls les membres du groupe peuvent uploader.
     *
     * @throws AuthorizationException
     */
    public function store(Request $request, Cours $cours, Classe $classe, Groupe $groupe): RedirectResponse
    {
        $this->verifierChaine($cours, $classe, $groupe);
        $groupe->load('classe.cours');
        $this->authorize('addMedia', $groupe);

        $request->validate([
            'fichier' => [
                'required',
                'file',
                'max:51200',
                'mimes:jpg,jpeg,png,webp,gif,pdf,doc,docx,mp3,wav,ogg,m4a,aac',
            ],
        ]);

        $file = $request->file('fichier');
        $ext = strtolower($file->getClientOriginalExtension());

        $type = match (true) {
            in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif']) => 'photo',
            in_array($ext, ['mp3', 'wav', 'ogg', 'm4a', 'aac']) => 'audio',
            default => 'document',
        };

        $meta = (new StoreUploadedFile)->execute(
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
     * @throws AuthorizationException
     */
    public function editer(Request $request, Cours $cours, Classe $classe, Groupe $groupe, GroupeMedia $media): RedirectResponse
    {
        $this->verifierChaine($cours, $classe, $groupe, $media);
        abort_if($media->type !== 'photo', 422);

        $groupe->load('classe.cours');
        $this->authorize('editerMedia', $groupe);

        // Les GIFs animés ne sont pas éditables sans perdre l'animation.
        $ext = strtolower(pathinfo($media->file_path, PATHINFO_EXTENSION));
        abort_if($ext === 'gif', 422, __('media.gif_non_editable'));

        $validated = $request->validate([
            'operation' => ['required', Rule::in(['crop', 'rotate', 'flip'])],
            // Crop
            'x' => ['required_if:operation,crop', 'nullable', 'integer', 'min:0'],
            'y' => ['required_if:operation,crop', 'nullable', 'integer', 'min:0'],
            'width' => ['required_if:operation,crop', 'nullable', 'integer', 'min:1'],
            'height' => ['required_if:operation,crop', 'nullable', 'integer', 'min:1'],
            // Rotate
            'angle' => ['required_if:operation,rotate', 'nullable', 'integer', Rule::in([90, 180, 270])],
            // Flip
            'direction' => ['required_if:operation,flip', 'nullable', Rule::in(['horizontal', 'vertical'])],
        ]);

        $fullPath = public_path($media->file_path);

        $manager = new ImageManager(new Driver);
        $image = $manager->decode($fullPath);

        match ($validated['operation']) {
            'crop' => $image->crop(
                (int) $validated['width'],
                (int) $validated['height'],
                (int) $validated['x'],
                (int) $validated['y'],
            ),
            'rotate' => $image->rotate((float) $validated['angle']),
            'flip' => $image->flip(
                $validated['direction'] === 'vertical'
                    ? Direction::VERTICAL
                    : Direction::HORIZONTAL
            ),
        };

        // Encoder à 90 % de qualité pour JPEG/WEBP, sans perte pour PNG.
        $image->save($fullPath, quality: 90);

        // Met à jour la taille puisque la re-compression a changé le poids.
        $media->update(['taille' => filesize($fullPath)]);

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
