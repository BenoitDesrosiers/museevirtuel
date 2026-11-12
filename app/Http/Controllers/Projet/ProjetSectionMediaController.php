<?php

namespace App\Http\Controllers\Projet;

use App\Actions\StoreUploadedFile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projet\StoreProjetSectionMediaRequest;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetRecherche;
use App\Models\ProjetSectionMedia;
use App\Models\TypeProjet;
use App\Models\TypeProjetSection;
use Illuminate\Http\RedirectResponse;

class ProjetSectionMediaController extends Controller
{
    /**
     * Attache un média (upload fichier ou URL) à une section de projet.
     *
     * Seuls les membres du groupe peuvent ajouter un média.
     * La section doit être de type 'video' ou 'audio'.
     */
    public function store(
        StoreProjetSectionMediaRequest $request,
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
        TypeProjetSection $section,
        StoreUploadedFile $fichiers,
    ): RedirectResponse {
        /** @var ProjetRecherche $projet */
        $projet = ProjetRecherche::where('groupe_id', $groupe->id)
            ->where('type_projet_id', $typeProjet->id)
            ->firstOrFail();

        abort_if(! in_array($section->type, ['video', 'audio']), 422);

        $validated = $request->validated();

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
            $fileMeta = $fichiers->execute(
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
     * Seul l'auteur du média, l'enseignant du cours ou un admin peut supprimer.
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
        abort_if($typeProjet->cours_id !== $cours->id, 404);
        abort_if($section->type_projet_id !== $typeProjet->id, 404);
        abort_if($media->section_id !== $section->id, 404);

        // Le média doit appartenir au projet de ce groupe, sinon on pourrait viser celui d'un autre groupe
        $projet = $media->projet;
        abort_if($projet->groupe_id !== $groupe->id || $projet->type_projet_id !== $typeProjet->id, 404);

        // L'auteur peut toujours supprimer son propre média
        if ($media->user_id !== auth()->id()) {
            $groupe->loadMissing('classe.cours');
            $this->authorize('deleteMedia', $groupe);
        }

        if ($media->source_type === 'upload') {
            $media->deleteWithFile();
        } else {
            $media->delete();
        }

        return back()->with('success', __('media.deleted'));
    }
}
