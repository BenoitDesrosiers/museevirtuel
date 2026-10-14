<?php

namespace App\Http\Controllers\Projet;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Projet\Concerns\AutoriseProjetRecherche;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetCritereCorrection;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use App\Models\TypeProjetCritere;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Corrections des critères d'évaluation par l'enseignant.
 */
class ProjetCritereCorrectionController extends Controller
{
    use AutoriseProjetRecherche;

    /**
     * Crée ou met à jour la correction d'un critère pour ce projet.
     *
     * `user_id` null = correction appliquée à tout le groupe.
     * Pour un critère positif, `verifie = true` et `points = null` accorde
     * automatiquement le pointage complet du critère.
     *
     * @throws HttpException
     */
    public function upsert(
        Request $request,
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
        TypeProjetCritere $critere,
    ): JsonResponse {
        $this->verifierTypeProjetAppartientCours($typeProjet, $cours);
        $this->autoriserEnseignant($cours, $classe, $groupe);
        abort_if($critere->type_projet_id !== $typeProjet->id, 404);

        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'points' => ['nullable', 'numeric', 'min:0'],
            'commentaire' => ['nullable', 'string', 'max:2000'],
            'verifie' => ['boolean'],
        ]);

        $projet = ProjetRecherche::firstOrCreate([
            'groupe_id' => $groupe->id,
            'type_projet_id' => $typeProjet->id,
        ]);

        // Quand on met à jour la correction groupe (user_id = null), supprimer les
        // overrides individuels afin que "attribuer à tous" s'applique réellement à tous.
        $clearedUserIds = [];
        if (! isset($validated['user_id'])) {
            $clearedUserIds = ProjetCritereCorrection::where('projet_id', $projet->id)
                ->where('critere_id', $critere->id)
                ->whereNotNull('user_id')
                ->pluck('user_id')
                ->all();

            if (! empty($clearedUserIds)) {
                ProjetCritereCorrection::where('projet_id', $projet->id)
                    ->where('critere_id', $critere->id)
                    ->whereNotNull('user_id')
                    ->delete();
            }
        }

        $correction = ProjetCritereCorrection::updateOrCreate(
            [
                'projet_id' => $projet->id,
                'critere_id' => $critere->id,
                'user_id' => $validated['user_id'] ?? null,
            ],
            [
                'points' => $validated['points'] ?? null,
                'commentaire' => $validated['commentaire'] ?? null,
                'verifie' => $request->boolean('verifie', false),
            ]
        );

        return response()->json([
            'message' => 'saved',
            'correction' => $correction->only('id', 'projet_id', 'critere_id', 'user_id', 'points', 'commentaire', 'verifie', 'source_id'),
            'cleared_user_ids' => $clearedUserIds,
        ]);
    }

    /**
     * Supprime une correction de critère et tous ses clones.
     *
     * @throws HttpException
     */
    public function destroy(
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
        ProjetCritereCorrection $correction,
    ): JsonResponse {
        $this->verifierTypeProjetAppartientCours($typeProjet, $cours);
        $this->autoriserEnseignant($cours, $classe, $groupe);

        $projet = $this->trouverProjet($groupe, $typeProjet);
        abort_if($correction->projet_id !== $projet->id, 404);

        // Supprimer d'abord les clones pour éviter la violation de contrainte FK
        $correction->clones()->delete();
        $correction->delete();

        return response()->json(['message' => 'deleted']);
    }

    /**
     * Clone une correction de groupe pour appliquer des points différents à un étudiant.
     *
     * La correction source peut être une correction de groupe (user_id = null) ou
     * individuelle. Le clone remplace toute correction individuelle existante pour
     * le même (projet, critère, étudiant).
     *
     * @throws HttpException
     */
    public function cloner(
        Request $request,
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
        ProjetCritereCorrection $correction,
    ): JsonResponse {
        $this->verifierTypeProjetAppartientCours($typeProjet, $cours);
        $this->autoriserEnseignant($cours, $classe, $groupe);

        $projet = $this->trouverProjet($groupe, $typeProjet);
        abort_if($correction->projet_id !== $projet->id, 404);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'points' => ['nullable', 'numeric', 'min:0'],
            'commentaire' => ['nullable', 'string', 'max:2000'],
            'verifie' => ['boolean'],
        ]);

        // Remplacer un éventuel clone existant pour ce (critère, étudiant)
        ProjetCritereCorrection::where('projet_id', $projet->id)
            ->where('critere_id', $correction->critere_id)
            ->where('user_id', $validated['user_id'])
            ->delete();

        $clone = ProjetCritereCorrection::create([
            'projet_id' => $projet->id,
            'critere_id' => $correction->critere_id,
            'user_id' => $validated['user_id'],
            'points' => $validated['points'] ?? null,
            'commentaire' => $validated['commentaire'] ?? null,
            'verifie' => $request->boolean('verifie', (bool) $correction->verifie),
            'source_id' => $correction->id,
        ]);

        return response()->json([
            'message' => 'cloned',
            'correction' => $clone->only('id', 'projet_id', 'critere_id', 'user_id', 'points', 'commentaire', 'verifie', 'source_id'),
        ]);
    }
}
