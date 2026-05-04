<?php

namespace App\Http\Controllers;

use App\Actions\StoreUploadedFile;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetRecherche;
use App\Models\ProjetSectionMedia;
use App\Models\TypeProjet;
use App\Models\TypeProjetSection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjetSectionMediaController extends Controller
{
    /**
     * Attache un média (upload fichier ou URL) à une section de projet.
     *
     * Seuls les membres du groupe peuvent ajouter un média.
     * La section doit être de type 'video' ou 'audio'.
     */
    public function store(
        Request $request,
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
        TypeProjetSection $section,
    ): RedirectResponse {
        abort_if($classe->cours_id !== $cours->id, 404);
        abort_if($groupe->classe_id !== $classe->id, 404);

        /** @var ProjetRecherche $projet */
        $projet = ProjetRecherche::where('groupe_id', $groupe->id)
            ->where('type_projet_id', $typeProjet->id)
            ->firstOrFail();

        abort_if($section->type_projet_id !== $typeProjet->id, 404);
        abort_if(! in_array($section->type, ['video', 'audio']), 422);

        // Vérifier que l'utilisateur est membre du groupe
        abort_unless(
            $groupe->membres()->where('user_id', auth()->id())->exists(),
            403
        );

        $validated = $request->validate([
            'source_type' => ['required', 'in:upload,url'],
            'url' => ['required_if:source_type,url', 'nullable', 'url', 'max:2048'],
            'fichier' => [
                'required_if:source_type,upload',
                'nullable',
                'file',
                'max:204800', // 200 Mo
                'mimes:mp4,webm,ogg,mp3,wav,m4a,aac',
            ],
        ]);

        $mediaMeta = [
            'projet_id' => $projet->id,
            'section_id' => $section->id,
            'type' => $section->type,
            'source_type' => $validated['source_type'],
            'user_id' => auth()->id(),
        ];

        if ($validated['source_type'] === 'url') {
            $mediaMeta['url'] = $validated['url'];
        } else {
            $file = $request->file('fichier');
            $fileMeta = (new StoreUploadedFile)->execute(
                $file,
                "medias/projets/{$projet->id}/sections/{$section->id}"
            );
            $mediaMeta = array_merge($mediaMeta, $fileMeta);
        }

        ProjetSectionMedia::create($mediaMeta);

        return back()->with('success', __('media.added'));
    }

    /**
     * Supprime un média de section.
     *
     * Seul l'auteur du média ou un enseignant/admin peut supprimer.
     */
    public function destroy(
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
        TypeProjetSection $section,
        ProjetSectionMedia $media,
    ): RedirectResponse {
        abort_if($classe->cours_id !== $cours->id, 404);
        abort_if($groupe->classe_id !== $classe->id, 404);
        abort_if($media->section_id !== $section->id, 404);

        $user = auth()->user();

        // L'auteur peut toujours supprimer son propre média
        if ($media->user_id !== $user->id) {
            abort_unless(
                in_array($user->role, ['enseignant', 'admin']),
                403
            );
        }

        if ($media->source_type === 'upload') {
            $media->deleteWithFile();
        } else {
            $media->delete();
        }

        return back()->with('success', __('media.deleted'));
    }
}
