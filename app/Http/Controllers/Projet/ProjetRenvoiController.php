<?php

namespace App\Http\Controllers\Projet;

use App\Concerns\ValidatesGroupeChain;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projet\StoreProjetRenvoiRequest;
use App\Http\Requests\Projet\UpdateProjetRenvoiRequest;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetRecherche;
use App\Models\ProjetRenvoi;
use App\Models\TypeProjet;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Renvois (notes de fin) du projet.
 */
class ProjetRenvoiController extends Controller
{
    use ValidatesGroupeChain;

    /**
     * Crée un nouveau renvoi (endnote) pour le projet.
     *
     * Le numéro est auto-incrémenté : max(numero) + 1 pour ce projet.
     * Seuls les membres du groupe peuvent créer des renvois.
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function store(StoreProjetRenvoiRequest $request, Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet): JsonResponse
    {
        $this->verifierChaineProjet($cours, $classe, $groupe, $typeProjet);

        $this->authorize('acceder', [ProjetRecherche::class, $groupe]);

        $projet = ProjetRecherche::firstOrNew([
            'groupe_id' => $groupe->id,
            'type_projet_id' => $typeProjet->id,
        ]);
        $this->authorize('editerContenu', $projet);
        $projet->save();

        $validated = $request->validated();

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
            'message' => __('reponse.created'),
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
     * @throws AuthorizationException
     */
    public function update(UpdateProjetRenvoiRequest $request, Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, ProjetRenvoi $renvoi): JsonResponse
    {
        $this->verifierChaineProjet($cours, $classe, $groupe, $typeProjet);

        $projet = ProjetRecherche::trouverPour($groupe, $typeProjet);

        abort_if($renvoi->projet_id !== $projet->id, 404);
        $this->authorize('editerContenu', $projet);

        $validated = $request->validated();

        $renvoi->update($validated);

        return response()->json(['message' => __('reponse.saved')]);
    }

    /**
     * Supprime un renvoi du projet.
     *
     * Les exposants référençant ce numéro dans le texte deviennent orphelins —
     * la détection visuelle (rouge) est gérée côté Vue via la liste renvois[].
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function destroy(Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, ProjetRenvoi $renvoi): JsonResponse
    {
        $this->verifierChaineProjet($cours, $classe, $groupe, $typeProjet);

        $projet = ProjetRecherche::trouverPour($groupe, $typeProjet);

        abort_if($renvoi->projet_id !== $projet->id, 404);
        $this->authorize('editerContenu', $projet);

        $renvoi->delete();

        return response()->json(['message' => __('reponse.deleted')]);
    }
}
