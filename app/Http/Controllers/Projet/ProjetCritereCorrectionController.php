<?php

namespace App\Http\Controllers\Projet;

use App\Concerns\ValidatesGroupeChain;
use App\Exceptions\TransactionEchoueeException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projet\ClonerProjetCritereCorrectionRequest;
use App\Http\Requests\Projet\UpsertProjetCritereCorrectionRequest;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetCritereCorrection;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use App\Models\TypeProjetCritere;
use App\Services\CorrectionCritereService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Corrections des critères d'évaluation par l'enseignant.
 */
class ProjetCritereCorrectionController extends Controller
{
    use ValidatesGroupeChain;

    /**
     * Crée ou met à jour la correction d'un critère pour ce projet.
     *
     * `user_id` null = correction appliquée à tout le groupe.
     * Pour un critère positif, `verifie = true` et `points = null` accorde
     * automatiquement le pointage complet du critère.
     *
     * @throws HttpException
     * @throws AuthorizationException
     * @throws Throwable
     */
    public function upsert(
        UpsertProjetCritereCorrectionRequest $request,
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
        TypeProjetCritere $critere,
        CorrectionCritereService $correctionCritereService,
    ): JsonResponse {
        $this->verifierChaineProjet($cours, $classe, $groupe, $typeProjet);
        $this->authorize('corriger', [ProjetRecherche::class, $groupe]);
        abort_if($critere->type_projet_id !== $typeProjet->id, 404);

        $projet = ProjetRecherche::firstOrCreate([
            'groupe_id' => $groupe->id,
            'type_projet_id' => $typeProjet->id,
        ]);

        try {
            $resultat = $correctionCritereService->enregistrer($projet, $critere, $request->validated(), $request->boolean('verifie', false));
        } catch (TransactionEchoueeException $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }

        return response()->json([
            'message' => __('reponse.saved'),
            'correction' => $this->formater($resultat['correction']),
            'cleared_user_ids' => $resultat['idsRetires'],
        ]);
    }

    /**
     * Supprime une correction de critère et tous ses clones.
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function destroy(
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
        ProjetCritereCorrection $correction,
        CorrectionCritereService $correctionCritereService,
    ): JsonResponse {
        $this->verifierChaineProjet($cours, $classe, $groupe, $typeProjet);
        $this->authorize('corriger', [ProjetRecherche::class, $groupe]);

        $projet = ProjetRecherche::trouverPour($groupe, $typeProjet);
        abort_if($correction->projet_id !== $projet->id, 404);

        try {
            $correctionCritereService->supprimer($correction);
        } catch (TransactionEchoueeException $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }

        return response()->json(['message' => __('reponse.deleted')]);
    }

    /**
     * Clone une correction de groupe pour appliquer des points différents à un étudiant.
     *
     * La correction source peut être une correction de groupe (user_id = null) ou
     * individuelle. Le clone remplace toute correction individuelle existante pour
     * le même (projet, critère, étudiant).
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function cloner(
        ClonerProjetCritereCorrectionRequest $request,
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
        ProjetCritereCorrection $correction,
        CorrectionCritereService $correctionCritereService,
    ): JsonResponse {
        $this->verifierChaineProjet($cours, $classe, $groupe, $typeProjet);
        $this->authorize('corriger', [ProjetRecherche::class, $groupe]);

        $projet = ProjetRecherche::trouverPour($groupe, $typeProjet);
        abort_if($correction->projet_id !== $projet->id, 404);

        $verifie = $request->boolean('verifie', (bool) $correction->verifie);
        try {
            $clone = $correctionCritereService->cloner($projet, $correction, $request->validated(), $verifie);
        } catch (TransactionEchoueeException $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }

        return response()->json([
            'message' => __('reponse.cloned'),
            'correction' => $this->formater($clone),
        ]);
    }

    /**
     * Retourne les champs d'une correction exposés au front.
     *
     * @return array<string, mixed>
     */
    private function formater(ProjetCritereCorrection $correction): array
    {
        return $correction->only('id', 'projet_id', 'critere_id', 'user_id', 'points', 'commentaire', 'verifie', 'source_id');
    }
}
