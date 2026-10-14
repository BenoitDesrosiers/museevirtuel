<?php

namespace App\Http\Controllers\Projet;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Projet\Concerns\AutoriseProjetRecherche;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetRenvoi;
use App\Models\ProjetRenvoiCommentaire;
use App\Models\TypeProjet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Commentaires de l'enseignant sur les renvois.
 */
class ProjetRenvoiCommentaireController extends Controller
{
    use AutoriseProjetRecherche;

    /**
     * Ajoute un commentaire de l'enseignant sur un renvoi (endnote) du projet.
     *
     * @throws HttpException
     */
    public function store(Request $request, Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, ProjetRenvoi $renvoi): JsonResponse
    {
        $this->verifierTypeProjetAppartientCours($typeProjet, $cours);
        $this->autoriserEnseignant($cours, $classe, $groupe);

        $projet = $this->trouverProjet($groupe, $typeProjet);
        abort_if($renvoi->projet_id !== $projet->id, 404);

        $validated = $request->validate([
            'contenu' => ['required', 'string', 'max:2000'],
        ]);

        $commentaire = ProjetRenvoiCommentaire::create([
            'renvoi_id' => $renvoi->id,
            'user_id' => auth()->id(),
            'contenu' => $validated['contenu'],
        ]);

        return response()->json([
            'message' => 'created',
            'commentaire' => $commentaire->only('id', 'contenu', 'user_id'),
        ], 201);
    }

    /**
     * Supprime un commentaire d'enseignant sur un renvoi.
     *
     * Vérifie que le commentaire appartient bien au renvoi passé en URL (anti-IDOR).
     *
     * @throws HttpException
     */
    public function destroy(Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, ProjetRenvoi $renvoi, ProjetRenvoiCommentaire $renvoiCommentaire): JsonResponse
    {
        $this->verifierTypeProjetAppartientCours($typeProjet, $cours);
        $this->autoriserEnseignant($cours, $classe, $groupe);

        $projet = $this->trouverProjet($groupe, $typeProjet);
        abort_if($renvoi->projet_id !== $projet->id, 404);
        abort_if($renvoiCommentaire->renvoi_id !== $renvoi->id, 404);

        $renvoiCommentaire->delete();

        return response()->json(['message' => 'deleted']);
    }
}
