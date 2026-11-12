<?php

namespace App\Http\Controllers\Projet;

use App\Concerns\ValidatesGroupeChain;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projet\UpsertProjetCommentaireRequest;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetCommentaire;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Commentaires de l'enseignant par champ du projet.
 */
class ProjetCommentaireController extends Controller
{
    use ValidatesGroupeChain;

    /**
     * Crée ou met à jour le commentaire de l'enseignant pour un champ donné.
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function upsert(UpsertProjetCommentaireRequest $request, Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet): JsonResponse
    {
        $this->verifierChaineProjet($cours, $classe, $groupe, $typeProjet);
        $this->authorize('corriger', [ProjetRecherche::class, $groupe]);

        $projet = ProjetRecherche::firstOrCreate([
            'groupe_id' => $groupe->id,
            'type_projet_id' => $typeProjet->id,
        ]);

        $commentaire = ProjetCommentaire::updateOrCreate(
            ['projet_id' => $projet->id, 'champ' => $request->validated('champ')],
            ['contenu' => $request->validated('contenu'), 'created_by' => auth()->id()],
        );

        return response()->json([
            'message' => __('reponse.saved'),
            'id' => $commentaire->id,
            'contenu' => $commentaire->contenu,
        ]);
    }

    /**
     * Supprime un commentaire de l'enseignant.
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function destroy(Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, ProjetCommentaire $commentaire): JsonResponse
    {
        $this->verifierChaineProjet($cours, $classe, $groupe, $typeProjet);
        $this->authorize('corriger', [ProjetRecherche::class, $groupe]);

        $projet = ProjetRecherche::trouverPour($groupe, $typeProjet);

        abort_if($commentaire->projet_id !== $projet->id, 404);
        $commentaire->delete();

        return response()->json(['message' => __('reponse.deleted')]);
    }
}
