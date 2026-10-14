<?php

namespace App\Http\Controllers\Projet;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Projet\Concerns\AutoriseProjetRecherche;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetRecherche;
use App\Models\ProjetRenvoi;
use App\Models\TypeProjet;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Renvois (notes de fin) du projet.
 */
class ProjetRenvoiController extends Controller
{
    use AutoriseProjetRecherche;

    /**
     * Crée un nouveau renvoi (endnote) pour le projet.
     *
     * Le numéro est auto-incrémenté : max(numero) + 1 pour ce projet.
     * Seuls les membres du groupe peuvent créer des renvois.
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

        $validated = $request->validate([
            'contenu' => ['nullable', 'string', 'max:2000'],
            'type_reference' => ['nullable', 'string', 'max:50'],
            'champs_reference' => ['nullable', 'array'],
        ]);

        $numero = ($projet->renvois()->max('numero') ?? 0) + 1;

        $payload = [
            'projet_id' => $projet->id,
            'contenu' => $validated['contenu'] ?? null,
            'type_reference' => $validated['type_reference'] ?? null,
            'champs_reference' => $validated['champs_reference'] ?? null,
        ];

        try {
            $renvoi = ProjetRenvoi::create(['numero' => $numero, ...$payload]);
        } catch (QueryException) {
            // Numéro en conflit (race condition) — on recalcule et on réessaie
            $numero = ($projet->renvois()->max('numero') ?? 0) + 1;
            $renvoi = ProjetRenvoi::create(['numero' => $numero, ...$payload]);
        }

        return response()->json([
            'message' => 'created',
            'renvoi' => $renvoi->only('id', 'numero', 'contenu', 'type_reference', 'champs_reference'),
        ], 201);
    }

    /**
     * Met à jour le contenu textuel et/ou le numéro d'un renvoi existant.
     *
     * Le champ `numero` est optionnel et utilisé lors de la renumérotation automatique
     * après suppression d'un renvoi. La contrainte unique (projet_id, numero) est respectée
     * car la renumérotation est effectuée dans l'ordre croissant (les trous sont comblés
     * avant d'assigner un numéro déjà existant).
     *
     * Vérifie que le renvoi appartient bien au projet du groupe (anti-IDOR).
     *
     * @throws HttpException
     */
    public function update(Request $request, Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, ProjetRenvoi $renvoi): JsonResponse
    {
        $this->verifierTypeProjetAppartientCours($typeProjet, $cours);

        $projet = $this->trouverProjet($groupe, $typeProjet);

        abort_if($renvoi->projet_id !== $projet->id, 404);
        $this->verifierEditionContenuAutorisee($cours, $classe, $groupe, $projet);

        $validated = $request->validate([
            'contenu' => ['nullable', 'string', 'max:2000'],
            'numero' => ['sometimes', 'integer', 'min:1'],
            'type_reference' => ['sometimes', 'nullable', 'string', 'max:50'],
            'champs_reference' => ['sometimes', 'nullable', 'array'],
        ]);

        $renvoi->update($validated);

        return response()->json(['message' => 'saved']);
    }

    /**
     * Supprime un renvoi du projet.
     *
     * Les exposants référençant ce numéro dans le texte deviennent orphelins —
     * la détection visuelle (rouge) est gérée côté Vue via la liste renvois[].
     *
     * @throws HttpException
     */
    public function destroy(Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, ProjetRenvoi $renvoi): JsonResponse
    {
        $this->verifierTypeProjetAppartientCours($typeProjet, $cours);

        $projet = $this->trouverProjet($groupe, $typeProjet);

        abort_if($renvoi->projet_id !== $projet->id, 404);
        $this->verifierEditionContenuAutorisee($cours, $classe, $groupe, $projet);

        $renvoi->delete();

        return response()->json(['message' => 'deleted']);
    }
}
