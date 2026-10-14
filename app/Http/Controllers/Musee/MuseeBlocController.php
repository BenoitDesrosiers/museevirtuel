<?php

namespace App\Http\Controllers\Musee;

use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\GroupeMedia;
use App\Models\MuseeBloc;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use App\Models\TypeProjetSection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MuseeBlocController extends Controller
{
    /**
     * Vérifie que la section appartient bien au TypeProjet et au cours,
     * et que le projet musée de ce groupe existe. Retourne le ProjetRecherche.
     *
     * @throws HttpException
     */
    private function verifierAcces(
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
        TypeProjetSection $section,
    ): ProjetRecherche {
        abort_if($classe->cours_id !== $cours->id, 404);
        abort_if($groupe->classe_id !== $classe->id, 404);
        abort_if($typeProjet->cours_id !== $cours->id, 404);
        abort_unless($typeProjet->isMusee(), 404);
        abort_if($section->type_projet_id !== $typeProjet->id, 404);

        $groupe->loadMissing('membres');
        abort_unless(
            $groupe->membres->contains('id', auth()->id()) || $cours->enseignant_id === auth()->id(),
            403,
        );

        return ProjetRecherche::where('groupe_id', $groupe->id)
            ->where('type_projet_id', $typeProjet->id)
            ->firstOrFail();
    }

    /**
     * Crée un nouveau bloc dans la section donnée du projet musée.
     *
     * L'ordre est auto-incrémenté (max existant + 1).
     *
     * @throws HttpException
     */
    public function store(
        Request $request,
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
        TypeProjetSection $section,
    ): RedirectResponse {
        $projet = $this->verifierAcces($cours, $classe, $groupe, $typeProjet, $section);

        $request->validate([
            'type' => ['required', 'string', 'in:texte,image,separateur,carrousel,video,audio'],
            // Identifiant de zone dans le canevas de la section — null = mode blocs libre
            'zone_id' => ['sometimes', 'nullable', 'string', 'max:36'],
            // Page multi-pages à laquelle rattacher ce bloc — null = bloc non paginé (rétrocompat)
            'musee_page_id' => ['sometimes', 'nullable', 'integer', 'exists:musee_pages,id'],
            // Paramètres optionnels de la palette — pré-remplissage du contenu à la création
            'groupe_video_id' => ['sometimes', 'nullable', 'integer', 'exists:groupe_videos,id'],
            'groupe_media_id' => ['sometimes', 'nullable', 'integer', 'exists:groupe_medias,id'],
            'image_id' => ['sometimes', 'nullable', 'integer', 'exists:musee_images,id'],
            // Contenu HTML initial pour les blocs texte (transcription insérée depuis la palette)
            'html' => ['sometimes', 'nullable', 'string'],
            'alt' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $type = $request->input('type');
        $contenu = $this->contenuParDefaut($type);

        // Pré-remplir avec le média ou le texte sélectionné depuis la palette du groupe
        if ($contenu !== null) {
            if ($type === MuseeBloc::TYPE_VIDEO && $request->has('groupe_video_id')) {
                $contenu['groupe_video_id'] = $request->integer('groupe_video_id');
            } elseif ($type === MuseeBloc::TYPE_AUDIO && $request->has('groupe_media_id')) {
                $contenu['pistes'][0]['groupe_media_id'] = $request->integer('groupe_media_id');
            } elseif ($type === MuseeBloc::TYPE_IMAGE && $request->has('image_id')) {
                // Image insérée depuis la bibliothèque via la palette
                $contenu['image_id'] = $request->integer('image_id');
                $contenu['alt'] = $request->input('alt', '');
                $contenu['legende'] = $request->input('legende', '');
            } elseif ($type === MuseeBloc::TYPE_TEXTE && $request->has('html')) {
                // Transcription insérée comme bloc texte depuis la palette
                $contenu['html'] = $request->input('html') ?? '';
            }
        }

        $maxOrdre = MuseeBloc::where('projet_recherche_id', $projet->id)
            ->where('section_id', $section->id)
            ->max('ordre') ?? 0;

        MuseeBloc::create([
            'projet_recherche_id' => $projet->id,
            'section_id' => $section->id,
            'musee_page_id' => $request->input('musee_page_id'),
            'type' => $type,
            'contenu' => $contenu,
            'ordre' => $maxOrdre + 1,
            'zone_id' => $request->input('zone_id'),
        ]);

        return back()->with('success', 'Bloc ajouté.');
    }

    /**
     * Met à jour le contenu JSON d'un bloc existant.
     *
     * @throws HttpException
     */
    public function update(
        Request $request,
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
        TypeProjetSection $section,
        MuseeBloc $bloc,
    ): RedirectResponse {
        $projet = $this->verifierAcces($cours, $classe, $groupe, $typeProjet, $section);
        abort_if($bloc->projet_recherche_id !== $projet->id, 404);
        abort_if($bloc->section_id !== $section->id, 404);

        $contenuValide = $this->validerContenu($request, $bloc->type);

        $bloc->update(['contenu' => $contenuValide]);

        return back()->with('success', 'Bloc mis à jour.');
    }

    /**
     * Supprime un bloc et renuméroter les suivants dans la même section.
     *
     * @throws HttpException
     */
    public function destroy(
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
        TypeProjetSection $section,
        MuseeBloc $bloc,
    ): RedirectResponse {
        $projet = $this->verifierAcces($cours, $classe, $groupe, $typeProjet, $section);
        abort_if($bloc->projet_recherche_id !== $projet->id, 404);
        abort_if($bloc->section_id !== $section->id, 404);

        $ordreSupp = $bloc->ordre;
        $bloc->delete();

        // Renuméroter les blocs dont l'ordre est supérieur au bloc supprimé
        MuseeBloc::where('projet_recherche_id', $projet->id)
            ->where('section_id', $section->id)
            ->where('ordre', '>', $ordreSupp)
            ->decrement('ordre');

        return back()->with('success', 'Bloc supprimé.');
    }

    /**
     * Réordonne les blocs d'une section selon le tableau d'IDs fourni.
     *
     * @throws HttpException
     */
    public function reorder(
        Request $request,
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
        TypeProjetSection $section,
    ): RedirectResponse {
        $projet = $this->verifierAcces($cours, $classe, $groupe, $typeProjet, $section);

        $request->validate([
            'ordre' => ['required', 'array'],
            'ordre.*' => ['integer'],
        ]);

        foreach ($request->input('ordre') as $position => $blocId) {
            MuseeBloc::where('id', $blocId)
                ->where('projet_recherche_id', $projet->id)
                ->where('section_id', $section->id)
                ->update(['ordre' => $position + 1]);
        }

        return back()->with('success', 'Blocs réordonnés.');
    }

    /**
     * Change la colonne d'affichage d'un bloc (1 ou 2) dans une section à 2 colonnes.
     *
     * Sans effet visuel si la section parente est en 1 colonne — la valeur
     * est tout de même mémorisée pour éviter une perte de données lors d'un
     * changement ultérieur de layout.
     *
     * @throws HttpException
     */
    public function updateColonne(
        Request $request,
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
        TypeProjetSection $section,
        MuseeBloc $bloc,
    ): RedirectResponse {
        $projet = $this->verifierAcces($cours, $classe, $groupe, $typeProjet, $section);
        abort_if($bloc->projet_recherche_id !== $projet->id, 404);
        abort_if($bloc->section_id !== $section->id, 404);

        $request->validate([
            'colonne' => ['required', 'integer', 'in:1,2'],
        ]);

        $bloc->update(['colonne' => $request->integer('colonne')]);

        return back()->with('success', 'Colonne mise à jour.');
    }

    /**
     * Met à jour uniquement les dimensions visuelles d'un bloc (hauteur et largeur en %).
     *
     * Appelé lors du redimensionnement au mouseup dans l'éditeur, séparé du contenu
     * pour ne pas interférer avec la validation de `update()`.
     *
     * @throws HttpException
     */
    public function updateDimensions(
        Request $request,
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
        TypeProjetSection $section,
        MuseeBloc $bloc,
    ): RedirectResponse {
        $projet = $this->verifierAcces($cours, $classe, $groupe, $typeProjet, $section);
        abort_if($bloc->projet_recherche_id !== $projet->id, 404);
        abort_if($bloc->section_id !== $section->id, 404);

        $request->validate([
            'hauteur_px' => ['nullable', 'integer', 'min:50', 'max:2000'],
            'largeur_pct' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        $bloc->update([
            'hauteur_px' => $request->input('hauteur_px'),
            'largeur_pct' => $request->input('largeur_pct'),
        ]);

        return back()->with('success', 'Dimensions mises à jour.');
    }

    /**
     * Retourne le contenu JSON par défaut pour un nouveau bloc selon son type.
     */
    private function contenuParDefaut(string $type): ?array
    {
        return match ($type) {
            MuseeBloc::TYPE_TEXTE => ['html' => ''],
            MuseeBloc::TYPE_IMAGE => ['image_id' => null, 'legende' => '', 'alt' => ''],
            MuseeBloc::TYPE_CARROUSEL => ['images' => []],
            MuseeBloc::TYPE_VIDEO => ['source' => 'upload', 'groupe_video_id' => null, 'url_externe' => null, 'legende' => ''],
            MuseeBloc::TYPE_AUDIO => ['pistes' => [['groupe_media_id' => null, 'titre' => '']], 'legende' => ''],
            MuseeBloc::TYPE_SEPARATEUR => null,
            default => null,
        };
    }

    /**
     * Valide et retourne le contenu JSON selon le type du bloc.
     */
    private function validerContenu(Request $request, string $type): ?array
    {
        return match ($type) {
            MuseeBloc::TYPE_TEXTE => (function () use ($request): array {
                $request->validate([
                    'html' => ['nullable', 'string'],
                    'image_ancree' => ['nullable', 'array'],
                    'image_ancree.image_id' => ['nullable', 'integer', 'exists:musee_images,id'],
                    'image_ancree.position' => ['nullable', 'string', 'in:gauche,droite'],
                ]);

                $result = ['html' => $request->input('html', '')];

                // N'inclure image_ancree que si explicitement fourni pour ne pas écraser les données existantes
                if ($request->has('image_ancree')) {
                    $result['image_ancree'] = $request->input('image_ancree');
                }

                return $result;
            })(),

            MuseeBloc::TYPE_IMAGE => (function () use ($request): array {
                $request->validate([
                    'image_id' => ['nullable', 'integer', 'exists:musee_images,id'],
                    'legende' => ['nullable', 'string', 'max:500'],
                    'alt' => ['nullable', 'string', 'max:255'],
                ]);

                return [
                    'image_id' => $request->input('image_id'),
                    'legende' => $request->input('legende', ''),
                    'alt' => $request->input('alt', ''),
                ];
            })(),

            MuseeBloc::TYPE_CARROUSEL => (function () use ($request): array {
                $request->validate([
                    'images' => ['nullable', 'array'],
                    'images.*.image_id' => ['required', 'integer', 'exists:musee_images,id'],
                    'images.*.legende' => ['nullable', 'string', 'max:500'],
                    'images.*.alt' => ['nullable', 'string', 'max:255'],
                ]);

                return ['images' => $request->input('images', [])];
            })(),

            MuseeBloc::TYPE_VIDEO => (function () use ($request): array {
                $request->validate([
                    'source' => ['required', 'string', 'in:upload,youtube,vimeo'],
                    'groupe_video_id' => ['nullable', 'integer', 'exists:groupe_videos,id'],
                    'url_externe' => ['nullable', 'string', 'max:500'],
                    'legende' => ['nullable', 'string', 'max:500'],
                ]);

                return [
                    'source' => $request->input('source', 'upload'),
                    'groupe_video_id' => $request->input('groupe_video_id'),
                    'url_externe' => $request->input('url_externe'),
                    'legende' => $request->input('legende', ''),
                ];
            })(),

            MuseeBloc::TYPE_AUDIO => (function () use ($request): array {
                $request->validate([
                    'pistes' => ['required', 'array', 'min:1', 'max:10'],
                    'pistes.*.groupe_media_id' => ['nullable', 'integer', 'exists:groupe_medias,id'],
                    'pistes.*.titre' => ['nullable', 'string', 'max:255'],
                    'legende' => ['nullable', 'string', 'max:500'],
                ]);

                // Vérifier que chaque média référencé est bien de type audio
                foreach ($request->input('pistes', []) as $piste) {
                    $mediaId = $piste['groupe_media_id'] ?? null;
                    if ($mediaId && ! GroupeMedia::where('id', $mediaId)->where('type', 'audio')->exists()) {
                        abort(422, 'Le média sélectionné n\'est pas un fichier audio.');
                    }
                }

                return [
                    'pistes' => $request->input('pistes', []),
                    'legende' => $request->input('legende', ''),
                ];
            })(),

            MuseeBloc::TYPE_SEPARATEUR => null,

            default => null,
        };
    }
}
