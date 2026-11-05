<?php

namespace App\Http\Controllers\Projet;

use App\Concerns\ValidatesGroupeChain;
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
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Conclusions individuelles des membres d'un projet.
 */
class ProjetConclusionController extends Controller
{
    use ValidatesGroupeChain;

    /**
     * Sauvegarde la conclusion individuelle d'un membre du groupe.
     *
     * N'importe quel membre du groupe peut modifier la conclusion d'un autre membre.
     * Le user_id cible doit être validé comme membre du groupe pour éviter l'IDOR.
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function update(UpdateProjetConclusionRequest $request, Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet): JsonResponse
    {
        $this->verifierChaineProjet($cours, $classe, $groupe, $typeProjet);

        // Charger les membres pour valider le user_id cible (toujours nécessaire, même pour l'enseignant)
        $groupe->load(['classe.cours', 'membres']);

        $this->authorize('acceder', [ProjetRecherche::class, $groupe]);

        $validated = $request->validated();

        abort_unless(
            $groupe->membres->contains('id', $validated['user_id']),
            422,
            'Cet étudiant n\'est pas membre du groupe.',
        );

        $projet = ProjetRecherche::firstOrCreate([
            'groupe_id' => $groupe->id,
            'type_projet_id' => $typeProjet->id,
        ]);

        $this->authorize('editerContenu', $projet);

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
