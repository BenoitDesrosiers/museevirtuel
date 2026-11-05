<?php

namespace App\Http\Controllers\Projet;

use App\Concerns\ValidatesGroupeChain;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projet\VoterProjetRemiseRequest;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use App\Services\RemiseProjetService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Remise du travail par le groupe : remise, annulation et vote des membres.
 */
class ProjetRemiseController extends Controller
{
    use ValidatesGroupeChain;

    /**
     * Enregistre la remise du travail par l'équipe d'étudiants.
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function store(Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, RemiseProjetService $remiseProjetService): JsonResponse
    {
        $this->verifierChaineProjet($cours, $classe, $groupe, $typeProjet);

        $groupe->loadMissing('membres');
        $this->authorize('contribuer', [ProjetRecherche::class, $groupe]);

        $projet = ProjetRecherche::firstOrCreate([
            'groupe_id' => $groupe->id,
            'type_projet_id' => $typeProjet->id,
        ]);

        // Associer la relation pour que peutEtreRemis() lise les paramètres depuis TypeProjet
        $projet->setRelation('typeProjet', $typeProjet);

        abort_if($projet->verrouille, 403, __('projet.verrouille'));
        abort_unless($projet->peutEtreRemis(), 422, __('projet.deja_remis_sans_multiples'));

        $contenusManquants = $remiseProjetService->remettre($projet, $groupe);

        if ($contenusManquants !== []) {
            return response()->json([
                'message' => __('projet.contenus_manquants'),
                'manquants' => $contenusManquants,
            ], 422);
        }

        return response()->json([
            'message' => __('projet.remis'),
            'remis_le' => $projet->remis_le->toIso8601String(),
        ]);
    }

    /**
     * Annule la remise du travail (enseignant seulement).
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function destroy(Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, RemiseProjetService $remiseProjetService): JsonResponse
    {
        $this->verifierChaineProjet($cours, $classe, $groupe, $typeProjet);
        $this->authorize('corriger', [ProjetRecherche::class, $groupe]);

        $projet = ProjetRecherche::trouverPour($groupe, $typeProjet);
        $projet->setRelation('typeProjet', $typeProjet);

        $remiseProjetService->annuler($projet);

        return response()->json(['message' => __('projet.remise_annulee')]);
    }

    /**
     * Enregistre ou met à jour le vote de remise d'un étudiant membre du groupe.
     *
     * Si tous les membres ont voté true, la remise est enregistrée de façon atomique.
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function voter(VoterProjetRemiseRequest $request, Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, RemiseProjetService $remiseProjetService): JsonResponse
    {
        $this->verifierChaineProjet($cours, $classe, $groupe, $typeProjet);

        $groupe->loadMissing('membres');
        $this->authorize('contribuer', [ProjetRecherche::class, $groupe]);

        $projet = ProjetRecherche::trouverPour($groupe, $typeProjet);
        $projet->setRelation('typeProjet', $typeProjet);

        abort_unless($projet->peutEtreRemis(), 422, __('projet.remise_impossible'));

        $remiseProjetService->voter($projet, $groupe, $request->user(), (bool) $request->validated('vote'));

        return response()->json([
            'message' => __('projet.vote_enregistre'),
            'remis_le' => $projet->fresh()->remis_le?->toIso8601String(),
        ]);
    }
}
