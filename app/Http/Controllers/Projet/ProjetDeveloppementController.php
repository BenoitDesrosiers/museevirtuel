<?php

namespace App\Http\Controllers\Projet;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Projet\Concerns\AutoriseProjetRecherche;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetDeveloppement;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Paragraphes de développement du projet (CRUD + réordonnancement).
 */
class ProjetDeveloppementController extends Controller
{
    use AutoriseProjetRecherche;

    /**
     * Ajoute un nouveau paragraphe de développement à la fin de la liste.
     *
     * @throws HttpException
     */
    public function store(Request $request, Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet): JsonResponse
    {
        $this->verifierTypeProjetAppartientCours($typeProjet, $cours);

        $projet = ProjetRecherche::firstOrCreate([
            'groupe_id' => $groupe->id,
            'type_projet_id' => $typeProjet->id,
        ]);
        $this->verifierEditionContenuAutorisee($cours, $classe, $groupe, $projet);

        $ordre = ($projet->developpements()->max('ordre') ?? 0) + 1;

        $dev = ProjetDeveloppement::create([
            'projet_id' => $projet->id,
            'ordre' => $ordre,
            'titre' => null,
            'contenu' => null,
        ]);

        return response()->json([
            'message' => 'created',
            'developpement' => $dev->only('id', 'ordre', 'titre', 'contenu'),
            'completion' => $projet->completion(),
        ], 201);
    }

    /**
     * Met à jour le titre et/ou le contenu d'un paragraphe de développement.
     *
     * @throws HttpException
     */
    public function update(Request $request, Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, ProjetDeveloppement $developpement): JsonResponse
    {
        $this->verifierTypeProjetAppartientCours($typeProjet, $cours);

        $projet = $this->trouverProjet($groupe, $typeProjet);

        abort_if($developpement->projet_id !== $projet->id, 404);
        $this->verifierEditionContenuAutorisee($cours, $classe, $groupe, $projet);

        $validated = $request->validate([
            'titre' => ['nullable', 'string', 'max:500'],
            'contenu' => ['nullable', 'string'],
        ]);

        $developpement->update($validated);

        if (array_key_exists('contenu', $validated) && $validated['contenu'] !== null) {
            $this->supprimerAnnotationsOrphelines(
                $projet,
                'developpement_'.$developpement->id,
                $validated['contenu']
            );
        }

        return response()->json([
            'message' => 'saved',
            'completion' => $projet->completion(),
        ]);
    }

    /**
     * Supprime un paragraphe de développement et réordonne les suivants.
     *
     * Refuse la suppression si c'est le dernier paragraphe (minimum : 1).
     *
     * @throws HttpException
     */
    public function destroy(Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, ProjetDeveloppement $developpement): JsonResponse
    {
        $this->verifierTypeProjetAppartientCours($typeProjet, $cours);

        $projet = $this->trouverProjet($groupe, $typeProjet);

        abort_if($developpement->projet_id !== $projet->id, 404);
        $this->verifierEditionContenuAutorisee($cours, $classe, $groupe, $projet);
        abort_if($projet->developpements()->count() <= 1, 422, 'Le projet doit conserver au moins un paragraphe.');

        $developpement->delete();

        $projet->developpements()->orderBy('ordre')->each(
            function (ProjetDeveloppement $dev, int $index): void {
                $dev->update(['ordre' => $index + 1]);
            }
        );

        return response()->json([
            'message' => 'deleted',
            'completion' => $projet->completion(),
        ]);
    }

    /**
     * Met à jour l'ordre de tous les paragraphes de développement d'un projet.
     *
     * @throws HttpException
     */
    public function reorder(Request $request, Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet): JsonResponse
    {
        $this->verifierTypeProjetAppartientCours($typeProjet, $cours);

        $projet = $this->trouverProjet($groupe, $typeProjet);

        $this->verifierEditionContenuAutorisee($cours, $classe, $groupe, $projet);

        $validated = $request->validate([
            'ordre' => ['required', 'array'],
            'ordre.*' => ['required', 'integer', 'exists:projet_developpements,id'],
        ]);

        foreach ($validated['ordre'] as $index => $id) {
            ProjetDeveloppement::where('id', $id)
                ->where('projet_id', $projet->id)
                ->update(['ordre' => $index + 1]);
        }

        return response()->json(['message' => 'reordered']);
    }
}
