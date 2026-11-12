<?php

namespace App\Http\Controllers\Projet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Projet\UpdateProjetConclusionRequest;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetConclusion;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

/**
 * Conclusions individuelles des membres d'un projet.
 */
class ProjetConclusionController extends Controller
{
    /**
     * Sauvegarde la conclusion individuelle d'un membre du groupe.
     *
     * @throws AuthorizationException
     */
    public function update(UpdateProjetConclusionRequest $request, Cours $cours, Classe $classe,
        Groupe $groupe, TypeProjet $typeProjet): JsonResponse
    {
        $validated = $request->validated();

        $projet = ProjetRecherche::firstOrNew([
            'groupe_id' => $groupe->id,
            'type_projet_id' => $typeProjet->id,
        ]);

        $this->authorize('editerContenu', $projet);
        $projet->save();

        $cle = ['projet_id' => $projet->id, 'user_id' => $validated['user_id']];
        if (isset($validated['section_id'])) {
            $cle['section_id'] = $validated['section_id'];
        }

        ProjetConclusion::updateOrCreate(
            $cle,
            ['contenu' => $validated['contenu']],
        );

        return response()->json(['message' => __('reponse.saved')]);
    }
}
