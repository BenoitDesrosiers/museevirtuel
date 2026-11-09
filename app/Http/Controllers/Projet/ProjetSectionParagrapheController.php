<?php

namespace App\Http\Controllers\Projet;

use App\Concerns\ValidatesGroupeChain;
use App\Exceptions\TransactionEchoueeException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commun\ReorderRequest;
use App\Http\Requests\Projet\UpdateProjetSectionParagrapheRequest;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetRecherche;
use App\Models\ProjetSectionParagraphe;
use App\Models\TypeProjet;
use App\Models\TypeProjetSection;
use App\Services\ParagraphesProjetService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Paragraphes des sections de type 'paragraphes' (CRUD + tri sur l'ordre).
 */
class ProjetSectionParagrapheController extends Controller
{
    use ValidatesGroupeChain;

    /**
     * Ajoute un nouveau paragraphe à la fin d'une section de type 'paragraphes'.
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function store(
        Cours $cours, Classe $classe, Groupe $groupe,
        TypeProjet $typeProjet, TypeProjetSection $section,
        ParagraphesProjetService $paragraphesProjetService): JsonResponse
    {
        $this->verifierChaineSection($cours, $classe, $groupe, $typeProjet, $section);
        $this->authorize('acceder', [ProjetRecherche::class, $groupe]);

        // Projet préparé en mémoire : une requête refusée ne doit pas le créer en base
        $projet = ProjetRecherche::firstOrNew([
            'groupe_id' => $groupe->id,
            'type_projet_id' => $typeProjet->id,
        ]);
        $this->authorize('editerContenu', $projet);
        $projet->save();

        $paragraphe = $paragraphesProjetService->ajouter($this->portee($projet, $section), ['section_id' => $section->id]);

        return response()->json([
            'message' => __('reponse.created'),
            'paragraphe' => $paragraphe->only('id', 'ordre', 'titre', 'contenu'),
        ], 201);
    }

    /**
     * Met à jour le titre et/ou le contenu d'un paragraphe de section.
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function update(
        UpdateProjetSectionParagrapheRequest $request, Cours $cours,
        Classe $classe, Groupe $groupe, TypeProjet $typeProjet,
        TypeProjetSection $section,
        ProjetSectionParagraphe $paragraphe, ParagraphesProjetService $paragraphesProjetService): JsonResponse
    {
        $projet = $this->autoriser($cours, $classe, $groupe, $typeProjet, $section, $paragraphe);

        $paragraphesProjetService->mettreAJour($paragraphe, $projet, 'section_paragraphe_'.$paragraphe->id, $request->validated());

        return response()->json(['message' => __('reponse.saved')]);
    }

    /**
     * Supprime un paragraphe de section et réordonne les suivants.
     *
     * Refuse la suppression si c'est le dernier paragraphe (minimum : 1).
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function destroy(
        Cours $cours, Classe $classe, Groupe $groupe,
        TypeProjet $typeProjet, TypeProjetSection $section,
        ProjetSectionParagraphe $paragraphe, ParagraphesProjetService $paragraphesProjetService): JsonResponse
    {
        $projet = $this->autoriser($cours, $classe, $groupe, $typeProjet, $section, $paragraphe);

        try {
            abort_unless($paragraphesProjetService->supprimer($paragraphe, $this->portee($projet, $section)), 422, __('projet.paragraphe_minimum'));
        } catch (TransactionEchoueeException $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }

        return response()->json(['message' => __('reponse.deleted')]);
    }

    /**
     * Met à jour l'ordre de tous les paragraphes d'une section de type 'paragraphes'.
     *
     * @throws HttpException
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function reorder(
        ReorderRequest $request, Cours $cours, Classe $classe,
        Groupe $groupe, TypeProjet $typeProjet,
        TypeProjetSection $section, ParagraphesProjetService $paragraphesProjetService): JsonResponse
    {
        $projet = $this->autoriser($cours, $classe, $groupe, $typeProjet, $section);

        try {
            $paragraphesProjetService->reordonner($this->portee($projet, $section), $request->ids());
        } catch (TransactionEchoueeException $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }

        return response()->json(['message' => __('reponse.reordered')]);
    }

    /**
     * Vérifie la chaîne d'URL jusqu'à la section, autorise la modification du contenu et
     * retourne le projet.
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    private function autoriser(
        Cours $cours, Classe $classe, Groupe $groupe,
        TypeProjet $typeProjet, TypeProjetSection $section,
        ?ProjetSectionParagraphe $paragraphe = null): ProjetRecherche
    {
        $this->verifierChaineSection($cours, $classe, $groupe, $typeProjet, $section);

        $projet = ProjetRecherche::trouverPour($groupe, $typeProjet);
        abort_if(
            $paragraphe !== null && ($paragraphe->projet_id !== $projet->id || $paragraphe->section_id !== $section->id),
            404
        );

        $this->authorize('editerContenu', $projet);

        return $projet;
    }

    /**
     * Paragraphes d'une section donnée dans ce projet : la liste sur laquelle porte l'ordre.
     *
     * @return HasMany<ProjetSectionParagraphe, ProjetRecherche>
     */
    private function portee(ProjetRecherche $projet, TypeProjetSection $section): HasMany
    {
        return $projet->sectionParagraphes()->where('section_id', $section->id);
    }
}
