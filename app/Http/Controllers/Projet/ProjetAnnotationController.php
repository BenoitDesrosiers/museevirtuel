<?php

namespace App\Http\Controllers\Projet;

use App\Concerns\ValidatesGroupeChain;
use App\Exceptions\TransactionEchoueeException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projet\DestroyProjetAnnotationRequest;
use App\Http\Requests\Projet\UpsertProjetAnnotationRequest;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetAnnotation;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use App\Services\AnnotationProjetService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
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
     * @throws HttpException si un paramètre de l'URL ou le champ ne correspond pas au projet
     * @throws AuthorizationException si l'utilisateur n'est pas l'enseignant du cours
     */
    public function upsert(
        UpsertProjetAnnotationRequest $request, Cours $cours,
        Classe $classe, Groupe $groupe, TypeProjet $typeProjet,
        AnnotationProjetService $annotationProjetService): JsonResponse
    {
        $this->verifierChaineProjet($cours, $classe, $groupe, $typeProjet);
        $this->authorize('corriger', [ProjetRecherche::class, $groupe]);

        $projet = ProjetRecherche::firstOrCreate([
            'groupe_id' => $groupe->id,
            'type_projet_id' => $typeProjet->id,
        ]);

        try {
            $annotation = $annotationProjetService->enregistrer($projet, $request->validated(), $request->user());
        } catch (TransactionEchoueeException $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }

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
     * @throws HttpException si un paramètre de l'URL ou le champ ne correspond pas au projet
     * @throws AuthorizationException si l'utilisateur n'est pas l'enseignant du cours
     */
    public function destroy(
        DestroyProjetAnnotationRequest $request, Cours $cours,
        Classe $classe, Groupe $groupe, TypeProjet $typeProjet,
        ProjetAnnotation $annotation, AnnotationProjetService $annotationProjetService): JsonResponse
    {
        $this->verifierChaineProjet($cours, $classe, $groupe, $typeProjet);
        $this->authorize('corriger', [ProjetRecherche::class, $groupe]);

        $projet = ProjetRecherche::trouverPour($groupe, $typeProjet);
        abort_if($annotation->projet_id !== $projet->id, 404);

        $validated = $request->validated();

        try {
            $annotationProjetService->supprimer($projet, $annotation, $validated['champ'], $validated['html']);
        } catch (TransactionEchoueeException $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }

        return response()->json(['message' => __('reponse.deleted')]);
    }
}
