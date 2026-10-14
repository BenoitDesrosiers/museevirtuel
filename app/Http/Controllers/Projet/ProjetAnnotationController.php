<?php

namespace App\Http\Controllers\Projet;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Projet\Concerns\AutoriseProjetRecherche;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetAnnotation;
use App\Models\ProjetDeveloppement;
use App\Models\ProjetRecherche;
use App\Models\ProjetRenvoi;
use App\Models\ProjetSectionContenu;
use App\Models\ProjetSectionParagraphe;
use App\Models\TypeProjet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Annotations inline de l'enseignant dans le contenu du projet.
 */
class ProjetAnnotationController extends Controller
{
    use AutoriseProjetRecherche;

    /** Pattern regex validant les noms de champs annotables (développement_{id}, section_{id}, section_paragraphe_{id} ou renvoi_{id}). */
    private const CHAMP_ANNOTABLE_REGEX = '/^(developpement_\d+|section_\d+|section_paragraphe_\d+|renvoi_\d+|page_titre_contenu|table_matieres_contenu)$/';

    /**
     * Crée ou met à jour une annotation inline sur un champ du projet.
     *
     * @throws HttpException si l'utilisateur n'est pas l'enseignant du cours
     */
    public function upsert(Request $request, Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet): JsonResponse
    {
        $this->verifierTypeProjetAppartientCours($typeProjet, $cours);
        $this->autoriserEnseignant($cours, $classe, $groupe);

        $validated = $request->validate([
            'champ' => ['required', 'string', 'regex:'.self::CHAMP_ANNOTABLE_REGEX],
            'commentaire_id' => ['required', 'string', 'max:36'],
            'contenu' => ['required', 'string', 'max:1000'],
            'html' => ['required', 'string'],
            'annotation_type' => ['required', 'string', 'in:commentaire,correction'],
            'points_malus' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'cible_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $projet = ProjetRecherche::firstOrCreate([
            'groupe_id' => $groupe->id,
            'type_projet_id' => $typeProjet->id,
        ]);

        $this->mettreAJourChampHtml($projet, $validated['champ'], $validated['html']);
        $this->supprimerAnnotationsOrphelines($projet, $validated['champ'], $validated['html']);

        preg_match_all('/data-comment-id="([^"]+)"/', $validated['html'], $allIds);
        $positionIndex = array_search($validated['commentaire_id'], $allIds[1], true);
        $position = $positionIndex !== false ? (int) $positionIndex : null;

        preg_match(
            '/<mark[^>]*data-comment-id="'.preg_quote($validated['commentaire_id'], '/').'[^>]*"[^>]*>(.*?)<\/mark>/si',
            $validated['html'],
            $markMatch
        );
        $motAnnote = isset($markMatch[1]) ? strip_tags($markMatch[1]) : null;

        $estCorrection = $validated['annotation_type'] === 'correction';

        $annotation = ProjetAnnotation::updateOrCreate(
            ['projet_id' => $projet->id, 'commentaire_id' => $validated['commentaire_id']],
            [
                'champ' => $validated['champ'],
                'contenu' => $validated['contenu'],
                'position' => $position,
                'mot_annote' => $motAnnote,
                'annotation_type' => $validated['annotation_type'],
                // points_malus et cible_user_id n'ont de sens que pour une correction
                'points_malus' => $estCorrection && isset($validated['points_malus'])
                    ? (float) $validated['points_malus']
                    : null,
                'cible_user_id' => $estCorrection ? ($validated['cible_user_id'] ?? null) : null,
                'user_id' => auth()->id(),
            ]
        );

        return response()->json([
            'message' => 'saved',
            'id' => $annotation->id,
            'commentaire_id' => $annotation->commentaire_id,
            'contenu' => $annotation->contenu,
            'annotation_type' => $annotation->annotation_type,
            'points_malus' => $annotation->points_malus !== null ? (float) $annotation->points_malus : null,
            'cible_user_id' => $annotation->cible_user_id,
            'user_id' => $annotation->user_id,
        ]);
    }

    /**
     * Supprime une annotation inline et met à jour le HTML du champ pour retirer la marque.
     *
     * @throws HttpException
     */
    public function destroy(Request $request, Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, ProjetAnnotation $annotation): JsonResponse
    {
        $this->verifierTypeProjetAppartientCours($typeProjet, $cours);
        $this->autoriserEnseignant($cours, $classe, $groupe);

        $projet = $this->trouverProjet($groupe, $typeProjet);

        abort_if($annotation->projet_id !== $projet->id, 404);

        $validated = $request->validate([
            'champ' => ['required', 'string', 'regex:'.self::CHAMP_ANNOTABLE_REGEX],
            'html' => ['required', 'string'],
        ]);

        $this->mettreAJourChampHtml($projet, $validated['champ'], $validated['html']);

        $annotation->delete();

        return response()->json(['message' => 'deleted']);
    }

    /**
     * Met à jour le contenu HTML d'un champ annotable.
     *
     * Supporte les champs fixes : `page_titre_contenu`, `table_matieres_contenu`.
     * Supporte les préfixes : `developpement_`, `section_paragraphe_`, `section_`, `renvoi_`.
     * Tout autre valeur est rejetée par le CHAMP_ANNOTABLE_REGEX en amont.
     *
     * @throws HttpException si la ressource n'appartient pas au projet
     */
    private function mettreAJourChampHtml(ProjetRecherche $projet, string $champ, string $html): void
    {
        if ($champ === 'page_titre_contenu') {
            $projet->update(['page_titre_contenu' => $html]);
        } elseif ($champ === 'table_matieres_contenu') {
            $projet->update(['table_matieres_contenu' => $html]);
        } elseif (str_starts_with($champ, 'developpement_')) {
            $devId = (int) mb_substr($champ, mb_strlen('developpement_'));
            $dev = ProjetDeveloppement::where('id', $devId)
                ->where('projet_id', $projet->id)
                ->firstOrFail();
            $dev->update(['contenu' => $html]);
        } elseif (str_starts_with($champ, 'section_paragraphe_')) {
            $paragId = (int) mb_substr($champ, mb_strlen('section_paragraphe_'));
            $paragraphe = ProjetSectionParagraphe::where('id', $paragId)
                ->where('projet_id', $projet->id)
                ->firstOrFail();
            $paragraphe->update(['contenu' => $html]);
        } elseif (str_starts_with($champ, 'section_')) {
            $sectionId = (int) mb_substr($champ, mb_strlen('section_'));
            ProjetSectionContenu::updateOrCreate(
                ['projet_id' => $projet->id, 'section_id' => $sectionId],
                ['contenu' => $html],
            );
        } elseif (str_starts_with($champ, 'renvoi_')) {
            // Le contenu du renvoi est mis à jour avec le HTML annoté (marks TipTap inclus).
            $renvoiId = (int) mb_substr($champ, mb_strlen('renvoi_'));
            ProjetRenvoi::where('id', $renvoiId)
                ->where('projet_id', $projet->id)
                ->firstOrFail()
                ->update(['contenu' => $html]);
        }
    }
}
