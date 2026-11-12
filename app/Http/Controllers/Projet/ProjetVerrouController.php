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
 * Verrou d'édition d'un projet, posé par l'enseignant.
 */
class ProjetVerrouController extends Controller
{
    use ValidatesGroupeChain;

    /**
     * Verrouille ou déverrouille le document pour l'édition par les étudiants.
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

        $projet->update(['verrouille' => ! $projet->verrouille]);

        return response()->json([
            'message' => __('reponse.toggled'),
            'verrouille' => (bool) $projet->verrouille,
        ]);
    }
}
