<?php

namespace App\Http\Controllers\Projet;

use App\Concerns\ValidatesGroupeChain;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projet\StoreProjetRenvoiCommentaireRequest;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetRecherche;
use App\Models\ProjetRenvoi;
use App\Models\ProjetRenvoiCommentaire;
use App\Models\TypeProjet;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Commentaires de l'enseignant sur les renvois.
 */
class ProjetRenvoiCommentaireController extends Controller
{
    use ValidatesGroupeChain;

    /**
     * Ajoute un commentaire de l'enseignant sur un renvoi (endnote) du projet.
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function store(StoreProjetRenvoiCommentaireRequest $request, Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, ProjetRenvoi $renvoi): JsonResponse
    {
        $this->verifierChaineProjet($cours, $classe, $groupe, $typeProjet);
        $this->authorize('corriger', [ProjetRecherche::class, $groupe]);

        $projet = ProjetRecherche::trouverPour($groupe, $typeProjet);
        abort_if($renvoi->projet_id !== $projet->id, 404);

        $validated = $request->validated();

        $commentaire = ProjetRenvoiCommentaire::create([
            'renvoi_id' => $renvoi->id,
            'user_id' => auth()->id(),
            'contenu' => $validated['contenu'],
        ]);

        return response()->json([
            'message' => __('reponse.created'),
            'commentaire' => $commentaire->only('id', 'contenu', 'user_id'),
        ], 201);
    }

    /**
     * Supprime un commentaire d'enseignant sur un renvoi.
     *
     * Vérifie que le commentaire appartient bien au renvoi passé en URL (anti-IDOR).
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function destroy(Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, ProjetRenvoi $renvoi, ProjetRenvoiCommentaire $renvoiCommentaire): JsonResponse
    {
        $this->verifierChaineProjet($cours, $classe, $groupe, $typeProjet);
        $this->authorize('corriger', [ProjetRecherche::class, $groupe]);

        $projet = ProjetRecherche::trouverPour($groupe, $typeProjet);
        abort_if($renvoi->projet_id !== $projet->id, 404);
        abort_if($renvoiCommentaire->renvoi_id !== $renvoi->id, 404);

        $renvoiCommentaire->delete();

        return response()->json(['message' => __('reponse.deleted')]);
    }
}
