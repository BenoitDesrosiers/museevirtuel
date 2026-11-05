<?php

namespace App\Http\Controllers\Projet;

use App\Actions\SupprimerAnnotationsOrphelines;
use App\Concerns\ValidatesGroupeChain;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projet\UpdateProjetSectionContenuRequest;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetRecherche;
use App\Models\ProjetSectionContenu;
use App\Models\TypeProjet;
use App\Models\TypeProjetSection;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Contenu HTML des sections dynamiques d'un projet.
 */
class ProjetSectionContenuController extends Controller
{
    use ValidatesGroupeChain;

    /**
     * Sauvegarde le contenu HTML d'une section dynamique pour un projet.
     *
     * Vérifie que la section appartient bien au TypeProjet du groupe (anti-IDOR).
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function update(UpdateProjetSectionContenuRequest $request, Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, TypeProjetSection $section, SupprimerAnnotationsOrphelines $supprimerOrphelines): JsonResponse
    {
        $this->verifierChaineProjet($cours, $classe, $groupe, $typeProjet);
        // Vérifier que la section appartient au TypeProjet passé en URL — évite l'IDOR
        abort_if($section->type_projet_id !== $typeProjet->id, 404);

        $projet = ProjetRecherche::trouverPour($groupe, $typeProjet);
        $this->authorize('editerContenu', $projet);

        $validated = $request->validated();

        ProjetSectionContenu::updateOrCreate(
            ['projet_id' => $projet->id, 'section_id' => $section->id],
            ['contenu' => $validated['contenu']],
        );

        if ($validated['contenu'] !== null) {
            $supprimerOrphelines->execute($projet, 'section_'.$section->id, $validated['contenu']);
        }

        return response()->json([
            'message' => __('reponse.saved'),
            'completion' => $projet->fresh()->load(['typeProjet.sections', 'sectionContenus'])->completion(),
        ]);
    }
}
