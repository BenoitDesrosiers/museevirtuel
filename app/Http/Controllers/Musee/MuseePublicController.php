<?php

namespace App\Http\Controllers\Musee;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExplorerMuseeRequest;
use App\Models\EpoqueHistorique;
use App\Models\GroupeMedia;
use App\Models\GroupeVideo;
use App\Models\MuseeBloc;
use App\Models\MuseeMeta;
use App\Models\MuseeVue;
use App\Models\RegionAdministrative;
use App\Models\Thematique;
use App\Models\TypeProjetSection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MuseePublicController extends Controller
{
    /**
     * Affiche la page d'accueil du musée virtuel public.
     *
     * Présente le musée et expose les 6 publications les plus récentes.
     */
    public function accueil(): Response
    {
        $base = $this->publieesMetas();

        $miseEnAvant = (clone $base)
            ->with(['projetRecherche.groupe.membres', 'epoque', 'thematique', 'regionAdministrative'])
            ->latest()
            ->take(6)
            ->get()
            ->map(fn (MuseeMeta $meta) => $this->formatMeta($meta));

        return Inertia::render('Musee/Public/Accueil', [
            'miseEnAvant' => $miseEnAvant,
            'total' => (clone $base)->count(),
        ]);
    }

    /**
     * Affiche la galerie publique des musées virtuels publiés.
     *
     * Supporte le filtrage multi-sélection par époque historique, région administrative
     * et thématique via les query params `epoque_ids[]`, `region_ids[]`, `thematique_ids[]`.
     * Les référentiels d'options sont exposés en totalité pour permettre de filtrer
     * même avant qu'un projet publié ne les utilise.
     */
    public function explorer(ExplorerMuseeRequest $request): Response
    {
        $epoqueIds = $request->validated('epoque_ids', []);
        $regionIds = $request->validated('region_ids', []);
        $thematiqueIds = $request->validated('thematique_ids', []);

        $base = $this->publieesMetas();

        $musees = (clone $base)
            ->with(['projetRecherche.groupe.membres', 'epoque', 'thematique', 'regionAdministrative'])
            ->when(! empty($epoqueIds), fn ($q) => $q->whereIn('epoque_historique_id', $epoqueIds))
            ->when(! empty($regionIds), fn ($q) => $q->whereIn('region_administrative_id', $regionIds))
            ->when(! empty($thematiqueIds), fn ($q) => $q->whereIn('thematique_id', $thematiqueIds))
            ->latest()
            ->paginate(12)
            ->withQueryString()
            ->through(fn (MuseeMeta $meta) => $this->formatMeta($meta));

        return Inertia::render('Musee/Public/Explorer', [
            'musees' => $musees,
            'filtres' => [
                'epoque_ids' => $epoqueIds,
                'region_ids' => $regionIds,
                'thematique_ids' => $thematiqueIds,
            ],
            // Les IDs validés sont déjà garantis entiers — pas besoin de cast supplémentaire.
            'options' => [
                'epoques' => EpoqueHistorique::orderBy('ordre')->get(['id', 'nom', 'annee_debut', 'annee_fin']),
                'regions' => RegionAdministrative::orderBy('ordre')->get(['id', 'nom']),
                'thematiques' => Thematique::whereNull('etablissement_id')->orderBy('nom')->get(['id', 'nom']),
            ],
        ]);
    }

    /**
     * Affiche la page publique d'invitation à contribuer en tant que témoin.
     */
    public function contribuer(): Response
    {
        return Inertia::render('Musee/Public/Contribuer');
    }

    // ─── Helpers privés ───────────────────────────────────────────────────────────

    /**
     * Retourne la requête de base pour les musées dont la publication est active.
     *
     * Utilisé par accueil() et explorer() pour éviter la répétition du whereHas.
     *
     * @return Builder<MuseeMeta>
     */
    private function publieesMetas(): Builder
    {
        return MuseeMeta::query()
            ->whereHas('projetRecherche.museePublication', fn ($q) => $q->where('est_publie', true));
    }

    /**
     * Sérialise un MuseeMeta en tableau pour les pages publiques (accueil, explorer).
     *
     * @return array<string, mixed>
     */
    private function formatMeta(MuseeMeta $meta): array
    {
        return [
            'slug' => $meta->slug,
            'titre' => $meta->entete_titre,
            'intro_texte' => $meta->intro_texte,
            'image_path' => $meta->intro_image_path ?? $meta->entete_image_path,
            'periode' => $meta->epoque?->only('id', 'nom'),
            'thematique' => $meta->thematique?->only('id', 'nom'),
            'region' => $meta->regionAdministrative?->only('id', 'nom'),
            'membres' => $meta->projetRecherche->groupe->membres
                ->map(fn ($m) => $m->prenom.' '.$m->nom)
                ->all(),
        ];
    }

    /**
     * Affiche la page publique du musée virtuel identifié par son slug.
     *
     * Enregistre une vue unique par IP/24h, charge le template visuel (CSS vars),
     * les sections avec leurs blocs, et la bibliothèque d'images.
     *
     * @throws HttpException
     */
    public function show(Request $request, string $slug): Response
    {
        $meta = MuseeMeta::where('slug', $slug)
            ->with([
                'projetRecherche.typeProjet.museeTemplate',
                'projetRecherche.groupe.membres',
                'projetRecherche.museePublication',
                'projetRecherche.museeImages',
                'epoque',
                'thematique',
                'regionAdministrative',
            ])
            ->firstOrFail();

        $projet = $meta->projetRecherche;

        // Les musées non publiés sont accessibles aux utilisateurs authentifiés (aperçu dans l'éditeur).
        // Les visiteurs anonymes ne voient que les musées explicitement publiés.
        if (! $projet->museePublication?->est_publie) {
            abort_unless(auth()->check(), 404);
        }

        // Enregistrer la visite uniquement pour les accès publics — pas les aperçus en édition
        if ($projet->museePublication?->est_publie) {
            MuseeVue::enregistrer($projet->id, $request->ip());
        }

        $typeProjet = $projet->typeProjet;
        $template = $typeProjet->museeTemplate;

        // Sections définies par l'enseignant, avec leurs blocs groupés
        $sectionsTypeProjet = TypeProjetSection::where('type_projet_id', $typeProjet->id)
            ->orderBy('ordre')
            ->get();

        $blocsParSection = $projet->museeBlocs()
            ->with('videoSegments')
            ->orderBy('ordre')
            ->get()
            ->groupBy('section_id');

        // Index des audios du groupe pour résoudre les URLs dans les blocs audio
        $audiosUrl = GroupeMedia::where('groupe_id', $projet->groupe_id)
            ->where('type', 'audio')
            ->get(['id', 'file_path', 'updated_at'])
            ->mapWithKeys(fn ($m) => [$m->id => $m->url]);

        $sections = $sectionsTypeProjet->map(fn ($section) => [
            'id' => $section->id,
            'label' => $section->label,
            'ordre' => $section->ordre,
            'layout' => $section->musee_layout,
            // Canevas de zones — null = mode blocs libre (rétrocompatibilité)
            'musee_canevas' => $section->musee_canevas,
            'blocs' => ($blocsParSection->get($section->id) ?? collect())->map(fn ($bloc) => [
                'id' => $bloc->id,
                'type' => $bloc->type,
                // Pour les blocs audio, on enrichit le contenu avec l'URL résolue
                'contenu' => $bloc->type === MuseeBloc::TYPE_AUDIO && $bloc->contenu
                    ? (function () use ($bloc, $audiosUrl): array {
                        $contenu = $bloc->contenu;
                        // Nouveau format multi-pistes — résoudre l'URL pour chaque piste
                        if (isset($contenu['pistes'])) {
                            return array_merge($contenu, [
                                'pistes' => array_map(
                                    fn ($p) => array_merge($p, [
                                        'url' => $audiosUrl[$p['groupe_media_id'] ?? 0] ?? null,
                                    ]),
                                    $contenu['pistes'],
                                ),
                            ]);
                        }

                        // Rétrocompatibilité : ancien format mono-piste (groupe_media_id au niveau racine)
                        return array_merge($contenu, [
                            'url' => $audiosUrl[$contenu['groupe_media_id'] ?? 0] ?? null,
                        ]);
                    })()
                    : $bloc->contenu,
                'ordre' => $bloc->ordre,
                'colonne' => $bloc->colonne ?? 1,
                'zone_id' => $bloc->zone_id,
                'segments' => $bloc->type === MuseeBloc::TYPE_VIDEO
                    ? $bloc->videoSegments->map->only('id', 'section_id', 'debut_secondes', 'fin_secondes', 'label')->toArray()
                    : [],
            ])->values()->toArray(),
        ]);

        // Bibliothèque d'images pour résoudre les image_id dans les blocs image/carrousel
        $images = $projet->museeImages->map(fn ($img) => [
            'id' => $img->id,
            'url' => $img->url,
            'alt' => $img->alt,
            'legende' => $img->legende,
            'crop_data' => $img->crop_data,
        ]);

        $membres = $projet->groupe->membres->map(fn ($m) => [
            'id' => $m->id,
            'prenom' => $m->prenom,
            'nom' => $m->nom,
        ]);

        // Index des sections pour la navigation segments → section_id → label
        $sectionsIndex = $sectionsTypeProjet->mapWithKeys(fn ($s) => [$s->id => $s->label]);

        return Inertia::render('Musee/Public/Show', [
            'sectionsIndex' => $sectionsIndex,
            'meta' => [
                'slug' => $meta->slug,
                'intro_texte' => $meta->intro_texte,
                'intro_image_path' => $meta->intro_image_path,
                'entete_titre' => $meta->entete_titre,
                'entete_sous_titre' => $meta->entete_sous_titre,
                'entete_overlay_couleur' => $meta->entete_overlay_couleur,
                'entete_image_position' => $meta->entete_image_position ?? 'center',
                'entete_image_path' => $meta->entete_image_path,
                'periode' => $meta->epoque?->only('id', 'nom'),
                'thematique' => $meta->thematique?->only('id', 'nom'),
                'region' => $meta->regionAdministrative?->only('id', 'nom'),
            ],
            'sections' => $sections,
            'images' => $images,
            'membres' => $membres,
            'cssVars' => $template?->toCssVariables() ?? [],
            'theme' => $template?->theme,
            'palette' => $template?->palette,
            'nbVues' => $projet->museeVues()->count(),
            'typeProjet' => $typeProjet->only('id', 'nom'),
        ]);
    }

    /**
     * Diffuse le fichier vidéo d'un bloc musée publié, sans authentification.
     *
     * Le slug du musée est vérifié — seules les vidéos dont le musée parent
     * est publié (`est_publie = true`) sont servies. Cela évite d'exposer
     * des vidéos de musées non publiés par simple guessing d'ID de bloc.
     *
     * @throws HttpException
     */
    public function streamVideo(string $slug, MuseeBloc $bloc): BinaryFileResponse
    {
        $meta = MuseeMeta::where('slug', $slug)
            ->with(['projetRecherche.museePublication'])
            ->firstOrFail();

        $projet = $meta->projetRecherche;

        abort_unless($projet->museePublication?->est_publie, 404);
        abort_if($bloc->projet_recherche_id !== $projet->id, 404);
        abort_if($bloc->type !== MuseeBloc::TYPE_VIDEO, 404);
        abort_unless(($bloc->contenu['source'] ?? '') === 'upload', 404);

        $videoId = $bloc->contenu['groupe_video_id'] ?? null;
        abort_if($videoId === null, 404);

        // Charger la GroupeVideo et vérifier qu'elle appartient au groupe du projet
        $video = GroupeVideo::findOrFail($videoId);
        abort_if($video->groupe_id !== $projet->groupe_id, 403);

        $path = $video->absolutePath();
        abort_unless(file_exists($path), 404);

        return response()->file($path);
    }
}
