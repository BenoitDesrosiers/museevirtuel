<?php

namespace App\Http\Controllers\Projet;

use App\Concerns\ValidatesGroupeChain;
use App\Exceptions\TransactionEchoueeException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commun\ReorderRequest;
use App\Http\Requests\Projet\SaveEntrevueConceptRequest;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\EntrevueConcept;
use App\Models\Groupe;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use App\Models\TypeProjetSection;
use App\Services\OrdreElements;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Concepts de la grille d'entrevue d'un projet.
 */
class EntrevueConceptController extends Controller
{
    use ValidatesGroupeChain;

    /**
     * Crée un nouveau concept dans une section de type 'entrevue'.
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function store(SaveEntrevueConceptRequest $request, Cours $cours, Classe $classe,
        Groupe $groupe, TypeProjet $typeProjet, TypeProjetSection $section,
        OrdreElements $ordres): JsonResponse
    {
        $this->verifierSectionEntrevue($cours, $classe, $groupe, $typeProjet, $section);

        $this->authorize('contribuer', [ProjetRecherche::class, $groupe]);

        $projet = ProjetRecherche::firstOrCreate([
            'groupe_id' => $groupe->id,
            'type_projet_id' => $typeProjet->id,
        ]);
        $this->authorize('create', [EntrevueConcept::class, $projet]);

        $validated = $request->validated();

        $ordre = $ordres->prochain($this->portee($projet, $section));

        $concept = EntrevueConcept::create([
            'projet_id' => $projet->id,
            'section_id' => $section->id,
            'label' => $validated['label'],
            'ordre' => $ordre,
        ]);

        return response()->json([
            'message' => __('reponse.created'),
            'concept' => $this->formaterConcept($concept),
        ], 201);
    }

    /**
     * Met à jour le label d'un concept existant.
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function update(SaveEntrevueConceptRequest $request, Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, TypeProjetSection $section, EntrevueConcept $concept): JsonResponse
    {
        $this->verifierSectionEntrevue($cours, $classe, $groupe, $typeProjet, $section);
        $this->verifierConcept($groupe, $typeProjet, $section, $concept);
        $this->authorize('update', $concept);

        $concept->update($request->validated());

        return response()->json(['message' => __('reponse.saved')]);
    }

    /**
     * Supprime un concept et réordonne les suivants.
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function destroy(Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, TypeProjetSection $section, EntrevueConcept $concept, OrdreElements $ordres): JsonResponse
    {
        $this->verifierSectionEntrevue($cours, $classe, $groupe, $typeProjet, $section);
        $projet = $this->verifierConcept($groupe, $typeProjet, $section, $concept);
        $this->authorize('delete', $concept);

        try {
            $ordres->supprimer($concept, $this->portee($projet, $section));
        } catch (TransactionEchoueeException $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }

        return response()->json(['message' => __('reponse.deleted')]);
    }

    /**
     * Met à jour l'ordre de tous les concepts d'une section de type 'entrevue'.
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function reorder(ReorderRequest $request, Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, TypeProjetSection $section, OrdreElements $ordres): JsonResponse
    {
        $this->verifierSectionEntrevue($cours, $classe, $groupe, $typeProjet, $section);

        $projet = ProjetRecherche::trouverPour($groupe, $typeProjet);
        $this->authorize('reorder', [EntrevueConcept::class, $projet]);

        try {
            $ordres->reordonner($this->portee($projet, $section), $request->ids());
        } catch (TransactionEchoueeException $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }

        return response()->json(['message' => __('reponse.reordered')]);
    }

    //  Méthodes privées

    /**
     * Vérifie la chaîne d'URL jusqu'à la section et que celle-ci est bien une entrevue.
     *
     * @throws HttpException
     */
    private function verifierSectionEntrevue(Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, TypeProjetSection $section): void
    {
        $this->verifierChaineSection($cours, $classe, $groupe, $typeProjet, $section);
        abort_if($section->type !== 'entrevue', 422, __('entrevue.section_invalide'));
    }

    /**
     * Vérifie que le concept appartient au projet du groupe et à la section, et retourne ce projet.
     *
     * @throws HttpException
     */
    private function verifierConcept(Groupe $groupe, TypeProjet $typeProjet, TypeProjetSection $section, EntrevueConcept $concept): ProjetRecherche
    {
        $projet = ProjetRecherche::trouverPour($groupe, $typeProjet);
        abort_if($concept->projet_id !== $projet->id || $concept->section_id !== $section->id, 404);

        // La policy lit le projet via le concept : on lui passe celui déjà chargé
        $concept->setRelation('projet', $projet);

        return $projet;
    }

    /**
     * Formate un concept avec ses lignes pour la réponse JSON.
     *
     * @return array<string, mixed>
     */
    private function formaterConcept(EntrevueConcept $concept): array
    {
        return [
            'id' => $concept->id,
            'label' => $concept->label,
            'ordre' => $concept->ordre,
            'lignes' => [],
        ];
    }

    /**
     * Concepts d'une section donnée dans ce projet : la liste sur laquelle porte l'ordre.
     *
     * @return Builder<EntrevueConcept>
     */
    private function portee(ProjetRecherche $projet, TypeProjetSection $section): Builder
    {
        return EntrevueConcept::where('projet_id', $projet->id)
            ->where('section_id', $section->id);
    }
}
