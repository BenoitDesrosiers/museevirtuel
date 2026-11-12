<?php

namespace App\Http\Controllers\Projet;

use App\Concerns\ValidatesGroupeChain;
use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Mode édition enseignant d'un projet.
 */
class ProjetModeEditionController extends Controller
{
    use ValidatesGroupeChain;

    /**
     * Active ou désactive le mode édition enseignant pour le projet d'une équipe.
     *
     * Quand actif, l'enseignant peut modifier directement le contenu du projet
     * (titre, sections, développements, conclusions, renvois) sans être membre.
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function __invoke(Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet): JsonResponse
    {
        $this->verifierChaineProjet($cours, $classe, $groupe, $typeProjet);
        $this->authorize('corriger', [ProjetRecherche::class, $groupe]);

        $projet = ProjetRecherche::firstOrCreate([
            'groupe_id' => $groupe->id,
            'type_projet_id' => $typeProjet->id,
        ]);

        $projet->update(['mode_edition_enseignant' => ! $projet->mode_edition_enseignant]);

        return response()->json([
            'message' => __('reponse.toggled'),
            'mode_edition_enseignant' => (bool) $projet->mode_edition_enseignant,
        ]);
    }
}
