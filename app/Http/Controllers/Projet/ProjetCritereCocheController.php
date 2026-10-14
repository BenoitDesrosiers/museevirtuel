<?php

namespace App\Http\Controllers\Projet;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Projet\Concerns\AutoriseProjetRecherche;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetCritereEtudiantCoche;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use App\Models\TypeProjetCritere;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Coches personnelles d'un étudiant sur les critères d'évaluation.
 */
class ProjetCritereCocheController extends Controller
{
    use AutoriseProjetRecherche;

    /**
     * Bascule la coche personnelle d'un étudiant pour un critère visible.
     *
     * La coche est un indicateur personnel de l'étudiant ; elle n'influence
     * pas la correction ni la note.
     *
     * @throws HttpException
     */
    public function toggle(
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
        TypeProjetCritere $critere,
    ): JsonResponse {
        abort_if($classe->cours_id !== $cours->id, 404);
        abort_if($groupe->classe_id !== $classe->id, 404);
        $this->verifierTypeProjetAppartientCours($typeProjet, $cours);
        abort_if($critere->type_projet_id !== $typeProjet->id, 404);

        $groupe->loadMissing('membres');
        abort_unless($groupe->membres->contains('id', auth()->id()), 403);
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
            'message' => 'toggled',
            'coche' => $estCoche,
        ]);
    }
}
