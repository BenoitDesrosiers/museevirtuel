<?php

namespace App\Http\Controllers\Projet;

use App\Concerns\ValidatesGroupeChain;
use App\Exceptions\TransactionEchoueeException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projet\UpdateEntrevueLigneRequest;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\EntrevueConcept;
use App\Models\EntrevueLigne;
use App\Models\Groupe;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use App\Models\TypeProjetSection;
use App\Services\OrdreElements;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Lignes (dimension, indicateur, questions) d'un concept d'entrevue.
 *
 * Les droits sont ceux du concept parent (EntrevueConceptPolicy::update).
 */
class EntrevueLigneController extends Controller
{
    use ValidatesGroupeChain;

    /**
     * Ajoute une nouvelle ligne (dimension/indicateur/questions) à un concept.
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function store(Request $request, Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, TypeProjetSection $section, EntrevueConcept $concept, OrdreElements $ordres): JsonResponse
    {
        $this->autoriserConcept($cours, $classe, $groupe, $typeProjet, $section, $concept);

        $ordre = $ordres->prochain($concept->lignes());

        $ligne = EntrevueLigne::create([
            'concept_id' => $concept->id,
            'dimension' => null,
            'indicateur' => null,
            'questions' => [],
            'ordre' => $ordre,
        ]);

        return response()->json([
            'message' => __('reponse.created'),
            'ligne' => $ligne->only('id', 'ordre', 'dimension', 'indicateur', 'questions'),
        ], 201);
    }

    /**
     * Met à jour les champs d'une ligne d'entrevue.
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function update(UpdateEntrevueLigneRequest $request, Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, TypeProjetSection $section, EntrevueConcept $concept, EntrevueLigne $ligne): JsonResponse
    {
        abort_if($ligne->concept_id !== $concept->id, 404);
        $this->autoriserConcept($cours, $classe, $groupe, $typeProjet, $section, $concept);

        $ligne->update($request->validated());

        return response()->json(['message' => __('reponse.saved')]);
    }

    /**
     * Supprime une ligne d'entrevue et réordonne les suivantes.
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function destroy(Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, TypeProjetSection $section, EntrevueConcept $concept, EntrevueLigne $ligne, OrdreElements $ordres): JsonResponse
    {
        abort_if($ligne->concept_id !== $concept->id, 404);
        $this->autoriserConcept($cours, $classe, $groupe, $typeProjet, $section, $concept);

        try {
            $ordres->supprimer($ligne, $concept->lignes());
        } catch (TransactionEchoueeException $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }

        return response()->json(['message' => __('reponse.deleted')]);
    }

    /**
     * Vérifie la chaîne d'URL jusqu'au concept puis autorise la modification de ce concept.
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    private function autoriserConcept(Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, TypeProjetSection $section, EntrevueConcept $concept): void
    {
        $this->verifierChaineSection($cours, $classe, $groupe, $typeProjet, $section);
        abort_if($section->type !== 'entrevue', 422, __('entrevue.section_invalide'));

        $projet = ProjetRecherche::trouverPour($groupe, $typeProjet);
        abort_if($concept->projet_id !== $projet->id || $concept->section_id !== $section->id, 404);

        $concept->setRelation('projet', $projet);
        $this->authorize('update', $concept);
    }
}
