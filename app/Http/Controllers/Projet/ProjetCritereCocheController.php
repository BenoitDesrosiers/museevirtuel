<?php

namespace App\Http\Controllers\Projet;

use App\Concerns\ValidatesGroupeChain;
use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetCritereEtudiantCoche;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use App\Models\TypeProjetCritere;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Coches personnelles d'un étudiant sur les critères d'évaluation.
 */
class ProjetCritereCocheController extends Controller
{
    use ValidatesGroupeChain;

    /**
     * Bascule la coche personnelle d'un étudiant pour un critère visible.
     *
     * La coche est un indicateur personnel de l'étudiant ; elle n'influence
     * pas la correction ni la note.
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function toggle(
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
        TypeProjetCritere $critere,
    ): JsonResponse {
        $this->verifierChaineProjet($cours, $classe, $groupe, $typeProjet);
        abort_if($critere->type_projet_id !== $typeProjet->id, 404);

        $this->authorize('contribuer', [ProjetRecherche::class, $groupe]);
        abort_unless((bool) $critere->visible, 403);

        $projet = ProjetRecherche::firstOrCreate([
            'groupe_id' => $groupe->id,
            'type_projet_id' => $typeProjet->id,
        ]);

        $coche = ProjetCritereEtudiantCoche::where('projet_id', $projet->id)
            ->where('critere_id', $critere->id)
            ->where('user_id', auth()->id())
            ->first();

        if ($coche) {
            $coche->delete();
            $estCoche = false;
        } else {
            ProjetCritereEtudiantCoche::create([
                'projet_id' => $projet->id,
                'critere_id' => $critere->id,
                'user_id' => auth()->id(),
            ]);
            $estCoche = true;
        }

        return response()->json([
            'message' => __('reponse.toggled'),
            'coche' => $estCoche,
        ]);
    }
}
