<?php

namespace App\Http\Controllers\Projet;

use App\Actions\SupprimerAnnotationsOrphelines;
use App\Concerns\ValidatesGroupeChain;
use App\Helpers\HtmlHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projet\DestroyProjetAnnotationRequest;
use App\Http\Requests\Projet\UpsertProjetAnnotationRequest;
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
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Annotations inline de l'enseignant dans le contenu du projet.
 */
class ProjetAnnotationController extends Controller
{
    use ValidatesGroupeChain;

    /**
     * Crée ou met à jour une annotation inline sur un champ du projet.
     *
     * @throws HttpException si l'utilisateur n'est pas l'enseignant du cours
     * @throws AuthorizationException
     */
    public function upsert(UpsertProjetAnnotationRequest $request, Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, SupprimerAnnotationsOrphelines $supprimerOrphelines): JsonResponse
    {
        $this->verifierChaineProjet($cours, $classe, $groupe, $typeProjet);
        $this->authorize('corriger', [ProjetRecherche::class, $groupe]);

        $validated = $request->validated();

        $projet = ProjetRecherche::firstOrCreate([
            'groupe_id' => $groupe->id,
            'type_projet_id' => $typeProjet->id,
        ]);

        $estCorrection = $validated['annotation_type'] === 'correction';

        $annotation = DB::transaction(function () use ($projet, $validated, $estCorrection, $supprimerOrphelines) {
            $this->mettreAJourChampHtml($projet, $validated['champ'], $validated['html']);
            $supprimerOrphelines->execute($projet, $validated['champ'], $validated['html']);

            return ProjetAnnotation::updateOrCreate(
                ['projet_id' => $projet->id, 'commentaire_id' => $validated['commentaire_id']],
                [
                    'champ' => $validated['champ'],
                    'contenu' => $validated['contenu'],
                    'position' => HtmlHelper::positionAnnotation($validated['html'], $validated['commentaire_id']),
                    'mot_annote' => HtmlHelper::motAnnote($validated['html'], $validated['commentaire_id']),
                    'annotation_type' => $validated['annotation_type'],
                    // points_malus et cible_user_id n'ont de sens que pour une correction
                    'points_malus' => $estCorrection && isset($validated['points_malus'])
                        ? (float) $validated['points_malus']
                        : null,
                    'cible_user_id' => $estCorrection ? ($validated['cible_user_id'] ?? null) : null,
                    'user_id' => auth()->id(),
                ]
            );
        });

        return response()->json([
            'message' => __('reponse.saved'),
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
     * @throws AuthorizationException
     */
    public function destroy(DestroyProjetAnnotationRequest $request, Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, ProjetAnnotation $annotation): JsonResponse
    {
        $this->verifierChaineProjet($cours, $classe, $groupe, $typeProjet);
        $this->authorize('corriger', [ProjetRecherche::class, $groupe]);

        $projet = ProjetRecherche::trouverPour($groupe, $typeProjet);

        abort_if($annotation->projet_id !== $projet->id, 404);

        $validated = $request->validated();

        DB::transaction(function () use ($projet, $validated, $annotation) {
            $this->mettreAJourChampHtml($projet, $validated['champ'], $validated['html']);
            $annotation->delete();
        });

        return response()->json(['message' => __('reponse.deleted')]);
    }

    /**
     * Met à jour le contenu HTML d'un champ annotable.
     *
     * Supporte les champs fixes : `page_titre_contenu`, `table_matieres_contenu`.
     * Supporte les préfixes : `developpement_`, `section_paragraphe_`, `section_`, `renvoi_`.
     * Tout autre valeur est rejetée par UpsertProjetAnnotationRequest::CHAMP_ANNOTABLE_REGEX en amont.
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
