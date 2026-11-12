<?php

namespace App\Http\Controllers\Projet;

use App\Concerns\ValidatesGroupeChain;
use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use App\Services\CalculNotesService;
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
    use ValidatesGroupeChain;

    /**
     * Active ou désactive la visibilité des corrections pour les étudiants.
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function toggleVisible(Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet): JsonResponse
    {
        $this->verifierChaineProjet($cours, $classe, $groupe, $typeProjet);
        $this->authorize('corriger', [ProjetRecherche::class, $groupe]);

        $projet = ProjetRecherche::firstOrCreate([
            'groupe_id' => $groupe->id,
            'type_projet_id' => $typeProjet->id,
        ]);

        $projet->update(['correction_visible' => ! $projet->correction_visible]);

        return response()->json([
            'message' => __('reponse.toggled'),
            'correction_visible' => (bool) $projet->correction_visible,
        ]);
    }

    /**
     * Affiche la page d'aperçu des notes finales du groupe (format DA + note).
     *
     * Accessible aux enseignants et admins uniquement.
     * Chaque ligne affiche le numéro DA de l'étudiant suivi de sa note calculée
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function apercuNotes(Cours $cours, Classe $classe, Groupe $groupe,
        TypeProjet $typeProjet, CalculNotesService $notes): Response
    {
        $this->verifierChaineProjet($cours, $classe, $groupe, $typeProjet);

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

        $projet = ProjetRecherche::trouverPour($groupe, $typeProjet);

        $typeProjet->load('criteres');
        $projet->load(['critereCorrections', 'annotations']);

        $lignes = $groupe->membres->map(fn ($membre) => [
            'da' => preg_replace('/\D/', '', (string) $membre->no_da),
            'prenom' => $membre->prenom,
            'nom' => $membre->nom,
            'note' => round($notes->points($typeProjet->criteres, $projet->critereCorrections, $projet->annotations, $membre->id), 2),
        ])->values();

        return Inertia::render('Projets/ApercuNotes', [
            'cours' => ['id' => $cours->id],
            'classe' => ['id' => $classe->id, 'cours_id' => $cours->id],
            'groupe' => ['id' => $groupe->id, 'numero' => $groupe->numero, 'classe_id' => $classe->id],
            'typeProjet' => ['id' => $typeProjet->id, 'nom' => $typeProjet->nom],
            'lignes' => $lignes,
        ]);
    }
}
