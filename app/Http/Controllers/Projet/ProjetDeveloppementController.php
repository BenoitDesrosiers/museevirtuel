<?php

namespace App\Http\Controllers\Projet;

use App\Concerns\ValidatesGroupeChain;
use App\Exceptions\TransactionEchoueeException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commun\ReorderRequest;
use App\Http\Requests\Projet\UpdateProjetDeveloppementRequest;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetDeveloppement;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use App\Services\ParagraphesProjetService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Paragraphes de développement du projet (CRUD + réordonnancement).
 */
class ProjetDeveloppementController extends Controller
{
    use ValidatesGroupeChain;

    /**
     * Ajoute un nouveau paragraphe de développement à la fin de la liste.
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function store(Cours $cours, Classe $classe,
        Groupe $groupe, TypeProjet $typeProjet,
        ParagraphesProjetService $paragraphesProjetService): JsonResponse
    {
        $this->verifierChaineProjet($cours, $classe, $groupe, $typeProjet);
        $this->authorize('acceder', [ProjetRecherche::class, $groupe]);

        // Projet préparé en mémoire : une requête refusée ne doit pas le créer en base
        $projet = ProjetRecherche::firstOrNew([
            'groupe_id' => $groupe->id,
            'type_projet_id' => $typeProjet->id,
        ]);
        $this->authorize('editerContenu', $projet);
        $projet->save();

        $developpement = $paragraphesProjetService->ajouter($projet->developpements());

        return response()->json([
            'message' => __('reponse.created'),
            'developpement' => $developpement->only('id', 'ordre', 'titre', 'contenu'),
            'completion' => $projet->completion(),
        ], 201);
    }

    /**
     * Met à jour le titre et/ou le contenu d'un paragraphe de développement.
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function update(
        UpdateProjetDeveloppementRequest $request,
        Cours $cours, Classe $classe,
        Groupe $groupe, TypeProjet $typeProjet,
        ProjetDeveloppement $developpement,
        ParagraphesProjetService $paragraphesProjetService): JsonResponse
    {
        $projet = $this->autoriser($cours, $classe, $groupe, $typeProjet, $developpement);

        $paragraphesProjetService->mettreAJour($developpement, $projet, 'developpement_'.$developpement->id, $request->validated());

        return response()->json([
            'message' => __('reponse.saved'),
            'completion' => $projet->completion(),
        ]);
    }

    /**
     * Supprime un paragraphe de développement et réordonne les suivants.
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function destroy(
        Cours $cours, Classe $classe,
        Groupe $groupe, TypeProjet $typeProjet,
        ProjetDeveloppement $developpement,
        ParagraphesProjetService $paragraphesProjetService): JsonResponse
    {
        $projet = $this->autoriser($cours, $classe, $groupe, $typeProjet, $developpement);

        try {
            abort_unless($paragraphesProjetService->supprimer($developpement, $projet->developpements()), 422, __('projet.developpement_minimum'));
        } catch (TransactionEchoueeException $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }

        return response()->json([
            'message' => __('reponse.deleted'),
            'completion' => $projet->completion(),
        ]);
    }

    /**
     * Met à jour l'ordre de tous les paragraphes de développement d'un projet.
     *
     * @throws HttpException
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function reorder(
        ReorderRequest $request, Cours $cours,
        Classe $classe, Groupe $groupe,
        TypeProjet $typeProjet, ParagraphesProjetService $paragraphesProjetService): JsonResponse
    {
        $projet = $this->autoriser($cours, $classe, $groupe, $typeProjet);

        try {
            $paragraphesProjetService->reordonner($projet->developpements(), $request->ids());
        } catch (TransactionEchoueeException $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }

        return response()->json(['message' => __('reponse.reordered')]);
    }

    /**
     * Vérifie la chaîne d'URL, autorise la modification du contenu et retourne le projet.
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    private function autoriser(
        Cours $cours, Classe $classe, Groupe $groupe,
        TypeProjet $typeProjet, ?ProjetDeveloppement $developpement = null): ProjetRecherche
    {
        $this->verifierChaineProjet($cours, $classe, $groupe, $typeProjet);

        $projet = ProjetRecherche::trouverPour($groupe, $typeProjet);
        abort_if($developpement !== null && $developpement->projet_id !== $projet->id, 404);

        $this->authorize('editerContenu', $projet);

        return $projet;
    }
}
