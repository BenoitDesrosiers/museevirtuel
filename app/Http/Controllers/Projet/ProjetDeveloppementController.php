<?php

namespace App\Http\Controllers\Projet;

use App\Actions\SupprimerAnnotationsOrphelines;
use App\Concerns\ValidatesGroupeChain;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commun\ReorderRequest;
use App\Http\Requests\Projet\UpdateProjetDeveloppementRequest;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetDeveloppement;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use App\Services\OrdreElements;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
    public function store(Request $request, Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, OrdreElements $ordres): JsonResponse
    {
        $this->verifierChaineProjet($cours, $classe, $groupe, $typeProjet);

        $this->authorize('acceder', [ProjetRecherche::class, $groupe]);

        $projet = ProjetRecherche::firstOrCreate([
            'groupe_id' => $groupe->id,
            'type_projet_id' => $typeProjet->id,
        ]);
        $this->authorize('editerContenu', $projet);

        $ordre = $ordres->prochain($projet->developpements());

        $dev = ProjetDeveloppement::create([
            'projet_id' => $projet->id,
            'ordre' => $ordre,
            'titre' => null,
            'contenu' => null,
        ]);

        return response()->json([
            'message' => __('reponse.created'),
            'developpement' => $dev->only('id', 'ordre', 'titre', 'contenu'),
            'completion' => $projet->completion(),
        ], 201);
    }

    /**
     * Met à jour le titre et/ou le contenu d'un paragraphe de développement.
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function update(UpdateProjetDeveloppementRequest $request, Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, ProjetDeveloppement $developpement, SupprimerAnnotationsOrphelines $supprimerOrphelines): JsonResponse
    {
        $this->verifierChaineProjet($cours, $classe, $groupe, $typeProjet);

        $projet = ProjetRecherche::trouverPour($groupe, $typeProjet);

        abort_if($developpement->projet_id !== $projet->id, 404);
        $this->authorize('editerContenu', $projet);

        $validated = $request->validated();

        $developpement->update($validated);

        if (array_key_exists('contenu', $validated) && $validated['contenu'] !== null) {
            $supprimerOrphelines->execute(
                $projet,
                'developpement_'.$developpement->id,
                $validated['contenu']
            );
        }

        return response()->json([
            'message' => __('reponse.saved'),
            'completion' => $projet->completion(),
        ]);
    }

    /**
     * Supprime un paragraphe de développement et réordonne les suivants.
     *
     * Refuse la suppression si c'est le dernier paragraphe (minimum : 1).
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function destroy(Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, ProjetDeveloppement $developpement, OrdreElements $ordres): JsonResponse
    {
        $this->verifierChaineProjet($cours, $classe, $groupe, $typeProjet);

        $projet = ProjetRecherche::trouverPour($groupe, $typeProjet);

        abort_if($developpement->projet_id !== $projet->id, 404);
        $this->authorize('editerContenu', $projet);
        abort_if($projet->developpements()->count() <= 1, 422, __('projet.developpement_minimum'));

        $ordres->supprimer($developpement, $projet->developpements());

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
     */
    public function reorder(ReorderRequest $request, Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, OrdreElements $ordres): JsonResponse
    {
        $this->verifierChaineProjet($cours, $classe, $groupe, $typeProjet);

        $projet = ProjetRecherche::trouverPour($groupe, $typeProjet);

        $this->authorize('editerContenu', $projet);

        $ordres->reordonner($projet->developpements(), $request->ids());

        return response()->json(['message' => __('reponse.reordered')]);
    }
}
