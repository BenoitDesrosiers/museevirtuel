<?php

namespace App\Policies;

use App\Models\Groupe;
use App\Models\ProjetRecherche;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Règles d'accès au projet de recherche d'un groupe.
 *
 * Les abilities qui reçoivent un Groupe s'appellent au niveau de la classe
 * (`[ProjetRecherche::class, $groupe]`) : elles doivent pouvoir s'exécuter
 * avant que le projet existe, pour qu'une requête refusée ne crée jamais
 * de ProjetRecherche via firstOrCreate.
 *
 * La cohérence des paramètres de route (cours → classe → groupe → typeProjet)
 * n'est pas vérifiée ici : elle relève de ValidatesGroupeChain (404).
 */
class ProjetRecherchePolicy
{
    /**
     * Détermine si l'utilisateur peut consulter ou alimenter le projet du groupe.
     *
     * Accessible aux membres du groupe et à l'enseignant du cours.
     */
    public function acceder(User $user, Groupe $groupe): bool
    {
        return $this->estEnseignantDuCours($user, $groupe) || $this->estMembre($user, $groupe);
    }

    /**
     * Détermine si l'utilisateur peut agir en tant qu'étudiant sur le projet
     * (le créer, le remettre, voter, cocher un critère, gérer les tâches).
     *
     * Réservé aux membres du groupe.
     */
    public function contribuer(User $user, Groupe $groupe): bool
    {
        return $this->estMembre($user, $groupe);
    }

    /**
     * Détermine si l'utilisateur peut signer le consentement vidéo du projet.
     *
     * Accessible aux membres du groupe et au témoin assigné.
     */
    public function consentir(User $user, Groupe $groupe): bool
    {
        return $groupe->personne_agee_id === $user->id || $this->estMembre($user, $groupe);
    }

    /**
     * Détermine si l'utilisateur peut corriger, annoter, verrouiller ou
     * administrer le projet du groupe.
     *
     * Réservé à l'enseignant du cours.
     */
    public function corriger(User $user, Groupe $groupe): bool
    {
        return $this->estEnseignantDuCours($user, $groupe);
    }

    /**
     * Détermine si l'utilisateur peut modifier le contenu rédactionnel du projet.
     *
     * L'enseignant du cours en mode édition passe outre le verrou et la remise.
     * Un membre du groupe ne peut écrire que si le projet n'est ni verrouillé
     * ni déjà remis (422 dans ce dernier cas, comme une règle métier).
     */
    public function editerContenu(User $user, ProjetRecherche $projet): Response
    {
        if ($projet->mode_edition_enseignant && $this->estEnseignantDuCours($user, $projet->groupe)) {
            return Response::allow();
        }

        return $this->contribuerAuContenu($user, $projet);
    }

    /**
     * Détermine si un membre du groupe peut écrire dans le projet.
     *
     * Refusé (403) si le projet est verrouillé, (422) s'il est déjà remis.
     */
    public function contribuerAuContenu(User $user, ProjetRecherche $projet): Response
    {
        if (! $this->estMembre($user, $projet->groupe)) {
            return Response::deny();
        }

        if ($projet->verrouille) {
            return Response::deny(__('projet.verrouille'));
        }

        // peutEtreRemis() retombe sur des colonnes héritées si typeProjet n'est pas chargé
        $projet->loadMissing('typeProjet');

        if (! $projet->peutEtreRemis()) {
            return Response::denyWithStatus(422, __('projet.deja_remis'));
        }

        return Response::allow();
    }

    /**
     * Vérifie si l'utilisateur est l'enseignant du cours auquel appartient le groupe.
     */
    private function estEnseignantDuCours(User $user, Groupe $groupe): bool
    {
        return $groupe->classe->cours->enseignant_id === $user->id;
    }

    /**
     * Vérifie si l'utilisateur est membre du groupe.
     */
    private function estMembre(User $user, Groupe $groupe): bool
    {
        if ($groupe->relationLoaded('membres')) {
            return $groupe->membres->contains('id', $user->id);
        }

        return $groupe->membres()->where('users.id', $user->id)->exists();
    }
}
