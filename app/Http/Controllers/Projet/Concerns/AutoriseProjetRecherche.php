<?php

namespace App\Http\Controllers\Projet\Concerns;

use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetAnnotation;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Vérifications d'appartenance et d'autorisation partagées par les contrôleurs du projet de recherche.
 */
trait AutoriseProjetRecherche
{
    /**
     * Retourne le ProjetRecherche correspondant au groupe et au type de projet, ou lève une 404.
     *
     * Charge toujours la relation typeProjet pour que peutEtreRemis() lise
     * les paramètres depuis le TypeProjet sans requête supplémentaire.
     *
     * @throws HttpException
     */
    private function trouverProjet(Groupe $groupe, TypeProjet $typeProjet): ProjetRecherche
    {
        return ProjetRecherche::where('groupe_id', $groupe->id)
            ->where('type_projet_id', $typeProjet->id)
            ->with('typeProjet')
            ->firstOrFail();
    }

    /**
     * Vérifie que le projet est modifiable : non-verrouillé et non encore remis.
     *
     * Factorise les deux guards répétés dans toutes les méthodes d'écriture.
     *
     * @throws HttpException
     */
    private function verifierProjetModifiable(ProjetRecherche $projet): void
    {
        abort_if($projet->verrouille, 403, 'Ce document est verrouillé.');
        abort_if(! $projet->peutEtreRemis(), 422, 'Ce travail a déjà été remis.');
    }

    /**
     * Vérifie que le TypeProjet appartient à l'enseignant du cours.
     *
     * Empêche l'accès à un TypeProjet d'un autre enseignant via manipulation d'URL.
     *
     * @throws HttpException
     */
    private function verifierTypeProjetAppartientCours(TypeProjet $typeProjet, Cours $cours): void
    {
        abort_if($typeProjet->enseignant_id !== $cours->enseignant_id, 404);
    }

    /**
     * Autorise la modification du contenu du projet par un membre du groupe
     * ou par l'enseignant lorsque le mode édition est activé.
     *
     * L'enseignant en mode édition bypasse le verrou et la contrainte de remise.
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    private function verifierEditionContenuAutorisee(Cours $cours, Classe $classe, Groupe $groupe, ProjetRecherche $projet): void
    {
        abort_if($classe->cours_id !== $cours->id, 404);
        abort_if($groupe->classe_id !== $classe->id, 404);

        // L'enseignant du cours en mode édition peut modifier le projet sans restriction
        if ($cours->enseignant_id === auth()->id() && $projet->mode_edition_enseignant) {
            return;
        }

        $groupe->loadMissing('classe.cours');
        $this->authorize('manageThematiques', $groupe);
        $this->verifierProjetModifiable($projet);
    }

    /**
     * Lève une exception si la classe/groupe n'appartiennent pas au cours
     * ou si l'utilisateur authentifié n'est pas l'enseignant de ce cours.
     *
     * @throws HttpException
     */
    private function autoriserEnseignant(Cours $cours, Classe $classe, Groupe $groupe): void
    {
        abort_if($classe->cours_id !== $cours->id, 404);
        abort_if($groupe->classe_id !== $classe->id, 404);
        abort_unless($cours->enseignant_id === auth()->id(), 403);
    }

    /**
     * Supprime les annotations d'un champ dont la marque n'est plus présente dans le HTML.
     */
    private function supprimerAnnotationsOrphelines(ProjetRecherche $projet, string $champ, string $html): void
    {
        preg_match_all('/data-comment-id="([^"]+)"/', $html, $matches);
        $idsPresents = $matches[1];

        ProjetAnnotation::where('projet_id', $projet->id)
            ->where('champ', $champ)
            ->when(
                ! empty($idsPresents),
                fn ($q) => $q->whereNotIn('commentaire_id', $idsPresents),
                fn ($q) => $q,
            )
            ->delete();
    }
}
