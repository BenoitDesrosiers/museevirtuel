<?php

namespace App\Http\Controllers;

use App\Actions\StoreUploadedFile;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetRecherche;
use App\Models\ProjetSchemaVisuel;
use App\Models\TypeProjet;
use App\Models\TypeProjetSection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjetSchemaVisuelController extends Controller
{
    /**
     * Crée ou met à jour le schéma visuel d'une section de projet (upsert).
     *
     * Le contenu JSON attendu :
     * {
     *   "image_centrale": "/path" | null,
     *   "zones": {
     *     "causes":       [{"id": "uuid", "texte": "...", "image": null}],
     *     "activites":    [...],
     *     "consequences": [...]
     *   }
     * }
     *
     * Seuls les membres du groupe (ou l'enseignant/admin) peuvent sauvegarder.
     * Le projet ne doit pas être verrouillé.
     */
    public function update(
        Request $request,
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
        TypeProjetSection $section,
    ): RedirectResponse {
        abort_if($classe->cours_id !== $cours->id, 404);
        abort_if($groupe->classe_id !== $classe->id, 404);
        abort_if($section->type_projet_id !== $typeProjet->id, 404);
        abort_if($section->type !== 'schema_visuel', 422);

        $user = $request->user();
        $estMembre = $groupe->membres()->where('user_id', $user->id)->exists();
        $estEnseignant = in_array($user->role, ['enseignant', 'admin']);

        abort_unless($estMembre || $estEnseignant, 403);

        /** @var ProjetRecherche $projet */
        $projet = ProjetRecherche::where('groupe_id', $groupe->id)
            ->where('type_projet_id', $typeProjet->id)
            ->firstOrFail();

        abort_if((bool) $projet->verrouille, 403);

        $validated = $request->validate([
            'contenu' => ['required', 'array'],
            'contenu.image_centrale' => ['nullable', 'string'],
            'contenu.zones' => ['required', 'array'],
            'contenu.zones.causes' => ['present', 'array'],
            'contenu.zones.activites' => ['present', 'array'],
            'contenu.zones.consequences' => ['present', 'array'],
            'contenu.zones.causes.*.id' => ['required', 'string'],
            'contenu.zones.causes.*.texte' => ['nullable', 'string', 'max:500'],
            'contenu.zones.causes.*.image' => ['nullable', 'string'],
            'contenu.zones.activites.*.id' => ['required', 'string'],
            'contenu.zones.activites.*.texte' => ['nullable', 'string', 'max:500'],
            'contenu.zones.activites.*.image' => ['nullable', 'string'],
            'contenu.zones.consequences.*.id' => ['required', 'string'],
            'contenu.zones.consequences.*.texte' => ['nullable', 'string', 'max:500'],
            'contenu.zones.consequences.*.image' => ['nullable', 'string'],
        ]);

        ProjetSchemaVisuel::updateOrCreate(
            ['projet_id' => $projet->id, 'section_id' => $section->id],
            ['contenu' => $validated['contenu']]
        );

        return back()->with('success', __('schema_visuel.saved'));
    }

    /**
     * Upload une image pour le schéma visuel (image centrale ou image de carte).
     *
     * Retourne un JSON `{"url": "/medias/schema/{projet_id}/{filename}"}`.
     * Le frontend intègre l'URL dans le contenu JSON avant de sauvegarder via update().
     */
    public function uploadImage(
        Request $request,
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
        TypeProjetSection $section,
    ): JsonResponse {
        abort_if($classe->cours_id !== $cours->id, 404);
        abort_if($groupe->classe_id !== $classe->id, 404);
        abort_if($section->type_projet_id !== $typeProjet->id, 404);
        abort_if($section->type !== 'schema_visuel', 422);

        $user = $request->user();
        $estMembre = $groupe->membres()->where('user_id', $user->id)->exists();
        $estEnseignant = in_array($user->role, ['enseignant', 'admin']);

        abort_unless($estMembre || $estEnseignant, 403);

        /** @var ProjetRecherche $projet */
        $projet = ProjetRecherche::where('groupe_id', $groupe->id)
            ->where('type_projet_id', $typeProjet->id)
            ->firstOrFail();

        abort_if((bool) $projet->verrouille, 403);

        $request->validate([
            'image' => ['required', 'image', 'max:5120', 'mimes:jpg,jpeg,png,gif,webp'],
        ]);

        $meta = (new StoreUploadedFile)->execute(
            $request->file('image'),
            "medias/schema/{$projet->id}"
        );

        return response()->json(['url' => '/'.$meta['file_path']]);
    }
}
