<?php

namespace App\Http\Controllers\Projet;

use App\Actions\StoreUploadedFile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projet\UpdateProjetSchemaVisuelRequest;
use App\Http\Requests\Projet\UploadProjetSchemaImageRequest;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetRecherche;
use App\Models\ProjetSchemaVisuel;
use App\Models\TypeProjet;
use App\Models\TypeProjetSection;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
        UpdateProjetSchemaVisuelRequest $request,
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
        TypeProjetSection $section,
    ): RedirectResponse {
        $projet = $this->autoriserEtTrouverProjet($cours, $classe, $groupe, $typeProjet, $section);

        $validated = $request->validated();

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
        UploadProjetSchemaImageRequest $request,
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
        TypeProjetSection $section,
        StoreUploadedFile $fichiers,
    ): JsonResponse {
        $projet = $this->autoriserEtTrouverProjet($cours, $classe, $groupe, $typeProjet, $section);

        $meta = $fichiers->execute(
            $request->file('image'),
            "medias/schema/{$projet->id}"
        );

        return response()->json(['url' => '/'.$meta['file_path']]);
    }

    /**
     * Vérifie la chaîne Cours → Classe → Groupe → TypeProjet → Section,
     * autorise l'utilisateur et retourne le projet non verrouillé du groupe.
     *
     * Seuls les membres du groupe, l'enseignant du cours et les admins passent :
     * un simple contrôle de rôle laisserait n'importe quel enseignant modifier le projet.
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    private function autoriserEtTrouverProjet(
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
        TypeProjetSection $section,
    ): ProjetRecherche {
        abort_if($classe->cours_id !== $cours->id, 404);
        abort_if($groupe->classe_id !== $classe->id, 404);
        abort_if($typeProjet->cours_id !== $cours->id, 404);
        abort_if($section->type_projet_id !== $typeProjet->id, 404);
        abort_if($section->type !== 'schema_visuel', 422);

        $groupe->loadMissing('classe.cours');
        $this->authorize('editerMedia', $groupe);

        /** @var ProjetRecherche $projet */
        $projet = ProjetRecherche::where('groupe_id', $groupe->id)
            ->where('type_projet_id', $typeProjet->id)
            ->firstOrFail();

        abort_if((bool) $projet->verrouille, 403);

        return $projet;
    }
}
