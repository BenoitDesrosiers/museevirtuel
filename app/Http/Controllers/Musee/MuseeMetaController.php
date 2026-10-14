<?php

namespace App\Http\Controllers\Musee;

use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\MuseeMeta;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MuseeMetaController extends Controller
{
    /**
     * Met à jour les métadonnées de catégorisation du projet musée :
     * texte d'intro, époque historique, thématique, région administrative.
     *
     * La MuseeMeta est auto-créée par l'observer ; cette méthode ne fait que mettre à jour.
     *
     * @throws HttpException
     */
    public function update(
        Request $request,
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
    ): RedirectResponse {
        abort_if($classe->cours_id !== $cours->id, 404);
        abort_if($groupe->classe_id !== $classe->id, 404);
        abort_if($typeProjet->cours_id !== $cours->id, 404);
        abort_unless($typeProjet->isMusee(), 404);

        $groupe->loadMissing('membres');
        abort_unless(
            $groupe->membres->contains('id', auth()->id()) || $cours->enseignant_id === auth()->id(),
            403,
        );

        $projet = ProjetRecherche::where('groupe_id', $groupe->id)
            ->where('type_projet_id', $typeProjet->id)
            ->firstOrFail();

        $validated = $request->validate([
            'intro_texte' => ['nullable', 'string', 'max:1000'],
            'epoque_historique_id' => ['nullable', 'integer', 'exists:epoques_historiques,id'],
            'thematique_id' => ['nullable', 'integer', 'exists:thematiques,id'],
            'region_administrative_id' => ['nullable', 'integer', 'exists:regions_administratives,id'],
        ]);

        MuseeMeta::updateOrCreate(
            ['projet_recherche_id' => $projet->id],
            $validated,
        );

        return back()->with('success', 'Métadonnées mises à jour.');
    }

    /**
     * Met à jour la section en-tête du projet musée :
     * titre, sous-titre, couleur de l'overlay et image de fond.
     *
     * L'image de fond (optionnelle) est stockée dans `public/musee/entetes/`.
     *
     * @throws HttpException
     */
    public function updateEntete(
        Request $request,
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
    ): RedirectResponse {
        abort_if($classe->cours_id !== $cours->id, 404);
        abort_if($groupe->classe_id !== $classe->id, 404);
        abort_if($typeProjet->cours_id !== $cours->id, 404);
        abort_unless($typeProjet->isMusee(), 404);

        $groupe->loadMissing('membres');
        abort_unless(
            $groupe->membres->contains('id', auth()->id()) || $cours->enseignant_id === auth()->id(),
            403,
        );

        $projet = ProjetRecherche::where('groupe_id', $groupe->id)
            ->where('type_projet_id', $typeProjet->id)
            ->firstOrFail();

        $validated = $request->validate([
            'entete_titre' => ['nullable', 'string', 'max:255'],
            'entete_sous_titre' => ['nullable', 'string', 'max:255'],
            'entete_overlay_couleur' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'entete_image_position' => ['nullable', 'string', 'in:center,top,bottom'],
            'entete_image' => ['nullable', 'image', 'max:4096'],
        ]);

        $meta = MuseeMeta::firstOrCreate(
            ['projet_recherche_id' => $projet->id],
        );

        if ($request->hasFile('entete_image')) {
            // Supprimer l'ancienne image si elle existe
            if ($meta->entete_image_path) {
                Storage::disk('public')->delete($meta->entete_image_path);
            }

            $path = $request->file('entete_image')->store('musee/entetes', 'public');
            $validated['entete_image_path'] = $path;
        }

        unset($validated['entete_image']);

        $meta->update($validated);

        return back()->with('success', 'En-tête mis à jour.');
    }
}
