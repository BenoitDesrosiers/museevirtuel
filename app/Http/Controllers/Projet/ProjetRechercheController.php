<?php

namespace App\Http\Controllers\Projet;

use App\Concerns\ValidatesGroupeChain;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Projet\Concerns\RendMuseeProjet;
use App\Http\Requests\Projet\UpdateProjetRechercheRequest;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use App\Services\ProjetRechercheIndexProps;
use App\Services\ProjetRechercheProps;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Projet de recherche d'un groupe : liste, éditeur, aperçu, contenu et réglages de l'enseignant.
 */
class ProjetRechercheController extends Controller
{
    use RendMuseeProjet;
    use ValidatesGroupeChain;

    /**
     * Affiche toutes les cartes de projets disponibles pour ce groupe.
     *
     * Retourne un tableau de TypeProjets accessibles de l'enseignant du cours,
     * chacun accompagné du ProjetRecherche correspondant (ou null si non encore créé).
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function index(Cours $cours, Classe $classe, Groupe $groupe,
        ProjetRechercheIndexProps $projetRechercheIndexProps): Response
    {
        abort_if($classe->cours_id !== $cours->id, 404);
        abort_if($groupe->classe_id !== $classe->id, 404);

        $groupe->load(['membres', 'classe.cours']);
        $this->authorize('view', $groupe);

        $user = auth()->user();
        $estEnseignant = $user->isEnseignant()
            && $cours->enseignant_id === $user->id;

        return Inertia::render('Projets/Index',
            $projetRechercheIndexProps->pourGroupe($cours, $classe, $groupe, $user, $estEnseignant));
    }

    /**
     * Affiche le projet partagé avec l'éditeur de contenu et les conclusions individuelles.
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function show(Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet,
        ProjetRechercheProps $projetRechercheProps): Response
    {
        $this->verifierChaineProjet($cours, $classe, $groupe, $typeProjet);

        $groupe->load(['membres', 'thematiques', 'classe.cours.enseignant']);
        $cours->loadMissing('enseignant');
        $this->authorize('view', $groupe);

        $user = auth()->user();
        $estEnseignant = $user->isEnseignant()
            && $cours->enseignant_id === $user->id;

        // Guard accessibilité : si le type de projet n'est pas accessible, les étudiants ne peuvent pas accéder
        if (! $estEnseignant && ! $user->isAdmin()) {
            abort_if(! $typeProjet->accessible, 403, __('projet.type_inaccessible'));
        }

        $projet = ProjetRecherche::firstOrCreate([
            'groupe_id' => $groupe->id,
            'type_projet_id' => $typeProjet->id,
        ]);

        if ($typeProjet->isMusee()) {
            return $this->renderMuseeShow($cours, $classe, $groupe, $typeProjet, $projet, $estEnseignant);
        }

        return Inertia::render('Projets/Show',
            $projetRechercheProps->pourEditeur($cours, $classe, $groupe, $typeProjet, $projet, $user, $estEnseignant));
    }

    /**
     * Affiche le projet en mode aperçu (lecture seule, sans annotations ni contrôles).
     *
     * Accessible aux membres du groupe et à l'enseignant du cours.
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function apercu(Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet,
        ProjetRechercheProps $projetRechercheProps): Response
    {
        $this->verifierChaineProjet($cours, $classe, $groupe, $typeProjet);

        $groupe->load(['membres', 'thematiques', 'classe.cours']);
        $this->authorize('view', $groupe);

        $user = auth()->user();
        $estEnseignant = $user->isEnseignant()
            && $cours->enseignant_id === $user->id;

        return Inertia::render('Projets/Apercu',
            $projetRechercheProps->pourApercu($cours, $classe, $groupe, $typeProjet, $estEnseignant));
    }

    /**
     * Met à jour le titre du projet et, le contenu manuel de la page titre
     * et de la table des matières (utilisés quand les flags de génération sont désactivés).
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function update(UpdateProjetRechercheRequest $request, Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet): JsonResponse
    {
        $this->verifierChaineProjet($cours, $classe, $groupe, $typeProjet);

        $validated = $request->validated();

        $existant = ProjetRecherche::where('groupe_id', $groupe->id)
            ->where('type_projet_id', $typeProjet->id)
            ->first();

        if ($existant !== null) {
            $this->authorize('editerContenu', $existant);
            $existant->update($validated);
            $projet = $existant;
        } else {
            // Le projet n'existe pas encore — seul un membre du groupe peut le créer
            $this->authorize('contribuer', [ProjetRecherche::class, $groupe]);
            $projet = ProjetRecherche::create([
                'groupe_id' => $groupe->id,
                'type_projet_id' => $typeProjet->id,
                ...$validated,
            ]);
        }

        return response()->json([
            'message' => __('reponse.saved'),
            'completion' => $projet->completion(),
        ]);
    }
}
