<?php

namespace App\Http\Controllers\Projet;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Projet\Concerns\RendMuseeProjet;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\MuseeBloc;
use App\Models\MuseeMeta;
use App\Models\ProjetCritereCorrection;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use App\Models\TypeProjetCritere;
use App\Models\TypeProjetSection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Page de correction d'un projet de type musée virtuel.
 */
class ProjetMuseeController extends Controller
{
    use RendMuseeProjet;

    /**
     * Affiche la page de correction côte-à-côte d'un musée virtuel.
     *
     * Rend le contenu complet du musée avec les liens externes mis en évidence,
     * et un panneau de correction listant les critères du type de projet.
     * Seul l'enseignant du cours peut accéder à cette page.
     *
     * @throws HttpException
     */
    public function correction(
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
    ): Response {
        abort_unless($cours->enseignant_id === auth()->id(), 403);
        abort_if($classe->cours_id !== $cours->id, 404);
        abort_if($groupe->classe_id !== $classe->id, 404);
        abort_if($typeProjet->cours_id !== $cours->id, 404);
        abort_unless($typeProjet->isMusee(), 404);

        $projet = ProjetRecherche::where('groupe_id', $groupe->id)
            ->where('type_projet_id', $typeProjet->id)
            ->with(['museePublication', 'groupe.membres'])
            ->firstOrFail();

        $meta = MuseeMeta::where('projet_recherche_id', $projet->id)
            ->with(['epoque', 'thematique', 'regionAdministrative'])
            ->firstOrFail();

        $typeProjet->loadMissing('museeTemplate');

        $sectionsTypeProjet = TypeProjetSection::where('type_projet_id', $typeProjet->id)
            ->orderBy('ordre')
            ->get();

        $blocsParSection = $projet->museeBlocs()
            ->with('videoSegments')
            ->orderBy('ordre')
            ->get()
            ->groupBy('section_id');

        $sections = $sectionsTypeProjet->map(fn ($section) => [
            'id' => $section->id,
            'label' => $section->label,
            'ordre' => $section->ordre,
            'blocs' => ($blocsParSection->get($section->id) ?? collect())->map(fn ($bloc) => [
                'id' => $bloc->id,
                'type' => $bloc->type,
                'contenu' => $bloc->contenu,
                'ordre' => $bloc->ordre,
                'hauteur_px' => $bloc->hauteur_px,
                'largeur_pct' => $bloc->largeur_pct,
                'segments' => $bloc->type === MuseeBloc::TYPE_VIDEO
                    ? $bloc->videoSegments->map->only('id', 'section_id', 'debut_secondes', 'fin_secondes', 'label')->toArray()
                    : [],
                // Utilisé pour surligner les blocs texte avec des liens externes en mode correction
                'aDesLiensExternes' => $bloc->aDesLiensExternes(),
            ])->values()->toArray(),
        ]);

        $images = $projet->museeImages()->get()->map(fn ($img) => [
            'id' => $img->id,
            'url' => $img->url,
            'alt' => $img->alt,
            'legende' => $img->legende,
            'crop_data' => $img->crop_data,
        ]);

        $criteres = TypeProjetCritere::where('type_projet_id', $typeProjet->id)
            ->orderBy('ordre')
            ->get();

        $corrections = ProjetCritereCorrection::where('projet_id', $projet->id)
            ->get()
            ->keyBy('critere_id');

        return Inertia::render('Musee/Correction', [
            'cours' => $cours->only('id', 'nom_cours', 'code', 'groupe'),
            'classe' => $classe->only('id', 'code', 'cours_id'),
            'groupe' => $groupe->only('id', 'code', 'classe_id'),
            'typeProjet' => $typeProjet->only('id', 'nom'),
            'projet' => $projet->only('id', 'titre_projet', 'verrouille', 'remis_le'),
            'meta' => $this->serializerMeta($meta),
            'sections' => $sections,
            'images' => $images,
            'cssVars' => $typeProjet->museeTemplate?->toCssVariables() ?? [],
            'membres' => $projet->groupe->membres->map(fn ($m) => $m->prenom.' '.$m->nom)->all(),
            'publication' => [
                'est_publie' => (bool) $projet->museePublication?->est_publie,
                'publie_le' => $projet->museePublication?->publie_le?->toISOString(),
            ],
            'criteres' => $criteres->map(fn ($c) => [
                'id' => $c->id,
                'type' => $c->type,
                'contenu' => $c->contenu,
                'pointage' => (float) $c->pointage,
                'section_id' => $c->section_id,
                'ordre' => $c->ordre,
                'correction' => isset($corrections[$c->id])
                    ? $corrections[$c->id]->only('id', 'points', 'commentaire', 'verifie')
                    : null,
            ]),
        ]);
    }

    // ─── Méthodes privées ─────────────────────────────────────────────────────
}
