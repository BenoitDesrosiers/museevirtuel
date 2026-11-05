<?php

namespace App\Http\Controllers\Projet;

use App\Actions\SupprimerAnnotationsOrphelines;
use App\Concerns\ValidatesGroupeChain;
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
use App\Services\OrdreElements;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
    public function store(Request $request, Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, TypeProjetSection $section, OrdreElements $ordres): JsonResponse
    {
        $this->verifierChaineProjet($cours, $classe, $groupe, $typeProjet);
        abort_if($section->type_projet_id !== $typeProjet->id, 404);

        $this->authorize('acceder', [ProjetRecherche::class, $groupe]);

        $projet = ProjetRecherche::firstOrCreate([
            'groupe_id' => $groupe->id,
            'type_projet_id' => $typeProjet->id,
        ]);
        $this->authorize('editerContenu', $projet);

        $ordre = $ordres->prochain($this->portee($projet, $section));

        $paragraphe = ProjetSectionParagraphe::create([
            'projet_id' => $projet->id,
            'section_id' => $section->id,
            'ordre' => $ordre,
            'titre' => null,
            'contenu' => null,
        ]);

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
    public function update(UpdateProjetSectionParagrapheRequest $request, Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, TypeProjetSection $section, ProjetSectionParagraphe $paragraphe, SupprimerAnnotationsOrphelines $supprimerOrphelines): JsonResponse
    {
        $this->verifierChaineProjet($cours, $classe, $groupe, $typeProjet);
        abort_if($section->type_projet_id !== $typeProjet->id, 404);

        $projet = ProjetRecherche::trouverPour($groupe, $typeProjet);

        abort_if($paragraphe->projet_id !== $projet->id || $paragraphe->section_id !== $section->id, 404);
        $this->authorize('editerContenu', $projet);

        $validated = $request->validated();

        $paragraphe->update($validated);

        if (array_key_exists('contenu', $validated) && $validated['contenu'] !== null) {
            $supprimerOrphelines->execute(
                $projet,
                'section_paragraphe_'.$paragraphe->id,
                $validated['contenu']
            );
        }

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
    public function destroy(Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, TypeProjetSection $section, ProjetSectionParagraphe $paragraphe, OrdreElements $ordres): JsonResponse
    {
        $this->verifierChaineProjet($cours, $classe, $groupe, $typeProjet);
        abort_if($section->type_projet_id !== $typeProjet->id, 404);

        $projet = ProjetRecherche::trouverPour($groupe, $typeProjet);

        abort_if($paragraphe->projet_id !== $projet->id || $paragraphe->section_id !== $section->id, 404);
        $this->authorize('editerContenu', $projet);

        abort_if($this->portee($projet, $section)->count() <= 1, 422, __('projet.paragraphe_minimum'));

        $ordres->supprimer($paragraphe, $this->portee($projet, $section));

        return response()->json(['message' => __('reponse.deleted')]);
    }

    /**
     * Met à jour l'ordre de tous les paragraphes d'une section de type 'paragraphes'.
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function reorder(ReorderRequest $request, Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, TypeProjetSection $section, OrdreElements $ordres): JsonResponse
    {
        $this->verifierChaineProjet($cours, $classe, $groupe, $typeProjet);
        abort_if($section->type_projet_id !== $typeProjet->id, 404);

        $projet = ProjetRecherche::trouverPour($groupe, $typeProjet);

        $this->authorize('editerContenu', $projet);

        $ordres->reordonner($this->portee($projet, $section), $request->ids());

        return response()->json(['message' => __('reponse.reordered')]);
    }

    /**
     * Paragraphes d'une section donnée dans ce projet : la liste sur laquelle porte l'ordre.
     *
     * @return Builder<ProjetSectionParagraphe>
     */
    private function portee(ProjetRecherche $projet, TypeProjetSection $section): Builder
    {
        return ProjetSectionParagraphe::where('projet_id', $projet->id)
            ->where('section_id', $section->id);
    }
}
