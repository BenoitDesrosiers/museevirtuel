<?php

namespace App\Http\Controllers\Projet\Concerns;

use App\Models\Classe;
use App\Models\Cours;
use App\Models\EpoqueHistorique;
use App\Models\Groupe;
use App\Models\GroupeMedia;
use App\Models\GroupeNote;
use App\Models\GroupeVideo;
use App\Models\MuseeBloc;
use App\Models\MuseeMeta;
use App\Models\MuseePage;
use App\Models\MuseePublication;
use App\Models\MuseeVue;
use App\Models\ProjetRecherche;
use App\Models\RegionAdministrative;
use App\Models\Thematique;
use App\Models\TypeProjet;
use App\Models\TypeProjetSection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Construction des pages musée (éditeur et correction) d'un projet de recherche.
 */
trait RendMuseeProjet
{
    /**
     * Rend la page éditeur pour un projet de type musée virtuel.
     *
     * Charge les métadonnées du musée (MuseeMeta) et les listes de catégorisation
     * (périodes, thématiques, régions) pour alimenter les sélecteurs du formulaire.
     * Le template visuel de l'enseignant est transmis comme variables CSS.
     *
     * @throws HttpException
     */
    private function renderMuseeShow(
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
        ProjetRecherche $projet,
        bool $estEnseignant,
    ): Response {
        // S'assurer que la MuseeMeta existe (l'observer peut avoir manqué lors des seeds/tests)
        $meta = MuseeMeta::firstOrCreate(
            ['projet_recherche_id' => $projet->id],
            ['slug' => MuseeMeta::genererSlug("musee-{$projet->groupe_id}-{$projet->type_projet_id}")],
        );

        $meta->load(['epoque', 'thematique', 'regionAdministrative']);

        $typeProjet->loadMissing('museeTemplate');

        // Sections du type de projet — définies par l'enseignant, ordonnées
        $sectionsTypeProjet = TypeProjetSection::where('type_projet_id', $typeProjet->id)
            ->orderBy('ordre')
            ->get();

        // Blocs de l'étudiant — groupés par section, avec segments pour les blocs vidéo
        $blocsParSection = $projet->museeBlocs()
            ->with('videoSegments')
            ->orderBy('ordre')
            ->get()
            ->groupBy('section_id');

        $sections = $sectionsTypeProjet->map(fn ($section) => [
            'id' => $section->id,
            'label' => $section->label,
            'ordre' => $section->ordre,
            'contraintes' => $section->musee_contraintes ?? [],
            'layout' => $section->musee_layout,
            // Canevas de zones — null = mode blocs libre (rétrocompatibilité)
            'musee_canevas' => $section->musee_canevas,
            // Configuration multi-pages
            'est_obligatoire' => (bool) $section->est_obligatoire,
            'est_reutilisable' => (bool) $section->est_reutilisable,
            'min_occurrences' => $section->min_occurrences ?? 1,
            'max_occurrences' => $section->max_occurrences,
            'blocs' => ($blocsParSection->get($section->id) ?? collect())->map(fn ($bloc) => [
                'id' => $bloc->id,
                'type' => $bloc->type,
                'contenu' => $bloc->contenu,
                'ordre' => $bloc->ordre,
                'colonne' => $bloc->colonne ?? 1,
                'hauteur_px' => $bloc->hauteur_px,
                'largeur_pct' => $bloc->largeur_pct,
                'zone_id' => $bloc->zone_id,
                'musee_page_id' => $bloc->musee_page_id,
                'segments' => $bloc->type === MuseeBloc::TYPE_VIDEO
                    ? $bloc->videoSegments->map->only('id', 'section_id', 'debut_secondes', 'fin_secondes', 'label')->toArray()
                    : [],
            ])->values()->toArray(),
        ]);

        // Pages multi-pages du musée étudiant — ordonnées
        $museePages = MuseePage::where('projet_recherche_id', $projet->id)
            ->orderBy('ordre')
            ->get()
            ->map(fn ($page) => [
                'id' => $page->id,
                'section_id' => $page->section_id,
                'titre' => $page->titre,
                'ordre' => $page->ordre,
            ]);

        // Bibliothèque d'images uploadées pour ce projet
        $images = $projet->museeImages()->get()->map(fn ($img) => [
            'id' => $img->id,
            'url' => $img->url,
            'alt' => $img->alt,
            'legende' => $img->legende,
            'crop_data' => $img->crop_data,
        ]);

        // Référentiels globaux québécois — pour les sélecteurs de catégorisation du musée.
        // Les thématiques sont les catégories CEGEP globales (etablissement_id null),
        // indépendantes des thématiques du groupe utilisées pour le système de témoins.
        $epoques = EpoqueHistorique::orderBy('ordre')->get(['id', 'nom', 'annee_debut', 'annee_fin']);
        $thematiques = Thematique::whereNull('etablissement_id')->orderBy('nom')->get(['id', 'nom']);
        $regionsAdministratives = RegionAdministrative::orderBy('ordre')->get(['id', 'nom']);

        $groupe->loadMissing('membres');
        $projet->loadMissing('museePublication');

        // L'édition est bloquée quand le musée est soumis (en attente) ou approuvé (publié)
        // — à moins que l'enseignant ait activé le mode édition manuelle.
        $blocqueParStatut = $projet->museePublication?->bloqueEditionEtudiants() ?? false;
        $peutEditer = (! $blocqueParStatut && $groupe->membres->contains('id', auth()->id()))
            || ($estEnseignant && (bool) $projet->mode_edition_enseignant);

        // Les vidéos, audios, notes et statistiques sont différés : ils ne sont pas inclus
        // dans la réponse initiale ni dans les rechargements partiels (only:['sections']).
        // Inertia les récupère automatiquement après le rendu de la page.
        $groupeId = $groupe->id;
        $projetId = $projet->id;

        return Inertia::render('Musee/Show', [
            'groupe' => $groupe->only('id', 'code', 'classe_id'),
            'classe' => $classe->only('id', 'code', 'cours_id'),
            'cours' => $cours->only('id', 'nom_cours', 'code', 'groupe'),
            'enseignant' => $cours->enseignant->only('id', 'prenom', 'nom'),
            'membres' => $groupe->membres->map->only('id', 'prenom', 'nom')->values(),
            'typeProjet' => $typeProjet->only('id', 'nom'),
            'projet' => $projet->only('id', 'titre_projet', 'verrouille', 'remis_le', 'mode_edition_enseignant'),
            'meta' => $this->serializerMeta($meta),
            'sections' => $sections,
            'museePages' => $museePages,
            'images' => $images,
            'template' => $typeProjet->museeTemplate?->toCssVariables() ?? [],
            'epoques' => $epoques,
            'thematiques' => $thematiques,
            'regionsAdministratives' => $regionsAdministratives,
            'peutEditer' => $peutEditer,
            'estEnseignant' => $estEnseignant,
            'verrouille' => (bool) $projet->verrouille,
            'publication' => [
                'est_publie' => (bool) $projet->museePublication?->est_publie,
                'statut' => $projet->museePublication?->statut ?? MuseePublication::STATUT_BROUILLON,
                'publie_le' => $projet->museePublication?->publie_le?->toISOString(),
                'soumis_le' => $projet->museePublication?->soumis_le?->toISOString(),
                'raison_rejet' => $projet->museePublication?->raison_rejet,
            ],
            // Propriétés différées — chargées par le client après le rendu initial.
            // Non recalculées lors des rechargements partiels (only:['sections']).
            'videos' => Inertia::defer(fn () => GroupeVideo::where('groupe_id', $groupeId)
                ->where('traitement_statut', GroupeVideo::TRAITEMENT_TERMINE)
                ->get()
                ->map(fn ($v) => [
                    'id' => $v->id,
                    'titre' => $v->titre,
                    'url' => $v->url,
                    'thumbnail_url' => $v->thumbnail_url,
                    'duree' => $v->duree,
                    'transcription_statut' => $v->transcription_statut,
                    // La transcription brute est exposée uniquement si terminée pour
                    // permettre l'insertion en bloc texte depuis la palette (tâche 1.4)
                    'transcription' => $v->transcription_statut === GroupeVideo::TRANSCRIPTION_TERMINEE
                        ? $v->transcription
                        : null,
                ])
            ),
            'audios' => Inertia::defer(fn () => GroupeMedia::where('groupe_id', $groupeId)
                ->where('type', 'audio')
                ->get()
                ->map(fn ($m) => [
                    'id' => $m->id,
                    'nom_original' => $m->nom_original,
                    'url' => $m->url,
                    'transcription_statut' => $m->transcription_statut,
                    'transcription' => $m->transcription_statut === GroupeMedia::TRANSCRIPTION_TERMINEE
                        ? $m->transcription
                        : null,
                ])
            ),
            // Notes du groupe — exposées dans la palette pour insertion comme blocs texte (tâche 1.4)
            'notes' => Inertia::defer(fn () => GroupeNote::where('groupe_id', $groupeId)
                ->with('auteur:id,prenom,nom')
                ->get(['id', 'contenu', 'user_id'])
            ),
            // Statistiques de vues publiques (enseignant uniquement)
            'stats' => Inertia::defer(fn () => $estEnseignant ? [
                'total' => MuseeVue::where('projet_recherche_id', $projetId)->count(),
                'last7' => MuseeVue::where('projet_recherche_id', $projetId)->where('vue_le', '>=', now()->subDays(7))->count(),
                'parJour' => MuseeVue::where('projet_recherche_id', $projetId)
                    ->where('vue_le', '>=', now()->subDays(30))
                    ->selectRaw('DATE(vue_le) as date, COUNT(*) as nb')
                    ->groupBy('date')
                    ->orderBy('date')
                    ->pluck('nb', 'date')
                    ->all(),
            ] : null),
        ]);
    }

    /**
     * Sérialise un MuseeMeta en tableau pour les vues enseignant (Show et Correction).
     *
     * Centralise la construction du tableau meta pour éviter la duplication entre
     * renderMuseeShow() et museeCorrection().
     *
     * @return array<string, mixed>
     */
    private function serializerMeta(MuseeMeta $meta): array
    {
        return [
            'id' => $meta->id,
            'slug' => $meta->slug,
            'intro_texte' => $meta->intro_texte,
            'intro_image_path' => $meta->intro_image_path,
            'entete_titre' => $meta->entete_titre,
            'entete_sous_titre' => $meta->entete_sous_titre,
            'entete_overlay_couleur' => $meta->entete_overlay_couleur,
            'entete_image_position' => $meta->entete_image_position ?? 'center',
            'entete_image_path' => $meta->entete_image_path,
            'epoque' => $meta->epoque?->only('id', 'nom'),
            'thematique' => $meta->thematique?->only('id', 'nom'),
            'region' => $meta->regionAdministrative?->only('id', 'nom'),
        ];
    }
}
