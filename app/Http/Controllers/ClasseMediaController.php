<?php

namespace App\Http\Controllers;

use App\Actions\StoreUploadedFile;
use App\Models\Classe;
use App\Models\ClasseMedia;
use App\Models\Cours;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ClasseMediaController extends Controller
{
    /**
     * Uploade un média et l'associe à la classe.
     *
     * La validation MIME réelle est assurée par Laravel.
     * Seuls les membres de la classe peuvent uploader.
     *
     * @throws AuthorizationException
     */
    public function store(Request $request, Cours $cours, Classe $classe): RedirectResponse
    {
        abort_if($classe->cours_id !== $cours->id, 404);

        $this->authorize('addMedia', $classe);

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
            "images/classes/{$classe->id}"
        );

        $classe->medias()->create([
            'user_id' => auth()->id(),
            'type' => $type,
            ...$meta,
        ]);

        return back()->with('success', __('media.added'));
    }

    /**
     * Supprime un média de la classe et son fichier physique.
     *
     * L'auteur du média, l'enseignant du cours et les admins peuvent supprimer.
     *
     * @throws AuthorizationException
     */
    public function destroy(Cours $cours, Classe $classe, ClasseMedia $media): RedirectResponse
    {
        abort_if($classe->cours_id !== $cours->id, 404);

        $user = auth()->user();

        // L'auteur peut supprimer son propre média — les autres cas passent par la Policy
        if ($media->user_id !== $user->id) {
            $this->authorize('deleteMedia', $classe);
        }

        $media->deleteWithFile();

        return back()->with('success', __('media.deleted'));
    }
}
