<?php

namespace App\Http\Controllers\Projet;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Projet\Concerns\AutoriseProjetRecherche;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Correction du projet par l'enseignant : visibilité de la correction et aperçu des notes.
 */
class ProjetCorrectionController extends Controller
{
    use AutoriseProjetRecherche;

    /**
     * Active ou désactive la visibilité des corrections pour les étudiants.
     *
     * @throws HttpException
     */
    public function toggleVisible(Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet): JsonResponse
    {
        $this->verifierTypeProjetAppartientCours($typeProjet, $cours);
        $this->autoriserEnseignant($cours, $classe, $groupe);

        $projet = ProjetRecherche::firstOrCreate([
            'groupe_id' => $groupe->id,
            'type_projet_id' => $typeProjet->id,
        ]);

        $projet->update(['correction_visible' => ! $projet->correction_visible]);

        return response()->json([
            'message' => 'toggled',
            'correction_visible' => (bool) $projet->correction_visible,
        ]);
    }

    /**
     * Affiche la page d'aperçu des notes finales du groupe (format DA + note).
     *
     * Accessible aux enseignants et admins uniquement.
     * Chaque ligne affiche le numéro DA de l'étudiant suivi de sa note calculée
     * (somme des critères positifs/négatifs corrigés, moins les malus d'annotations).
     * La logique de calcul est identique à celle du panneau notesParMembre dans Show.vue.
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function apercuNotes(Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet): Response
    {
        abort_if($classe->cours_id !== $cours->id, 404);
        abort_if($groupe->classe_id !== $classe->id, 404);
        $this->verifierTypeProjetAppartientCours($typeProjet, $cours);

        $groupe->load(['membres', 'classe.cours']);
        $this->authorize('view', $groupe);

        $user = auth()->user();
        /**
         * Il faudra retirer la portion $user->isEnseignant()
         * && en production.
         */
        abort_unless(
            $user->isAdmin() || ($user->isEnseignant() && $cours->enseignant_id === $user->id),
            403,
        );

        $projet = $this->trouverProjet($groupe, $typeProjet);

        $typeProjet->load('criteres');
        $projet->load(['critereCorrections', 'annotations']);

        // Index des corrections par critère_id pour éviter des boucles N+1
        $correctionsByCritere = $projet->critereCorrections->groupBy('critere_id');

        $lignes = $groupe->membres->map(function ($membre) use ($typeProjet, $correctionsByCritere, $projet) {
            $obtenu = 0.0;

            foreach ($typeProjet->criteres as $critere) {
                $corrections = $correctionsByCritere->get($critere->id, collect());

                // Correction individuelle prime sur la correction de groupe (même logique que Show.vue)
                $correction = $corrections->first(fn ($c) => $c->user_id === $membre->id)
                    ?? $corrections->first(fn ($c) => $c->user_id === null);

                if ($correction === null) {
                    continue;
                }

                $pts = (float) ($correction->points ?? 0);
                if ($critere->type === 'positif') {
                    $obtenu += $pts;
                } else {
                    $obtenu -= $pts;
                }
            }

            // Malus d'annotation : s'applique si cible_user_id = null (tous) ou = cet étudiant
            $malus = $projet->annotations
                ->filter(fn ($a) => $a->points_malus !== null
                    && ($a->cible_user_id === null || $a->cible_user_id === $membre->id))
                ->sum(fn ($a) => (float) $a->points_malus);

            return [
                'da' => preg_replace('/\D/', '', (string) $membre->no_da),
                'prenom' => $membre->prenom,
                'nom' => $membre->nom,
                'note' => round(($obtenu - $malus) * 100) / 100,
            ];
        })->values();

        return Inertia::render('Projets/ApercuNotes', [
            'cours' => ['id' => $cours->id],
            'classe' => ['id' => $classe->id, 'cours_id' => $cours->id],
            'groupe' => ['id' => $groupe->id, 'numero' => $groupe->numero, 'classe_id' => $classe->id],
            'typeProjet' => ['id' => $typeProjet->id, 'nom' => $typeProjet->nom],
            'lignes' => $lignes,
        ]);
    }

    // ─── Renvois (endnotes) ───────────────────────────────────────────────────
}
