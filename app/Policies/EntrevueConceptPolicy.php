<?php

namespace App\Policies;

use App\Models\EntrevueConcept;
use App\Models\ProjetRecherche;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Règles d'accès à la grille d'entrevue d'un projet (concepts et leurs lignes).
 *
 * La grille est un travail d'étudiants : seuls les membres du groupe y écrivent,
 * et seulement tant que le projet n'est ni verrouillé ni remis. Ces règles sont
 * celles de ProjetRecherchePolicy::contribuerAuContenu, réutilisées ici.
 *
 * Les lignes n'ont pas de policy propre : modifier une ligne revient à modifier
 * son concept.
 */
class EntrevueConceptPolicy
{
    /**
     * Injecte la policy du projet, qui porte les règles de modification.
     */
    public function __construct(private ProjetRecherchePolicy $projets) {}

    /**
     * Détermine si l'utilisateur peut ajouter un concept au projet.
     */
    public function create(User $user, ProjetRecherche $projet): Response
    {
        return $this->projets->contribuerAuContenu($user, $projet);
    }

    /**
     * Détermine si l'utilisateur peut modifier un concept ou gérer ses lignes.
     */
    public function update(User $user, EntrevueConcept $concept): Response
    {
        return $this->projets->contribuerAuContenu($user, $concept->projet);
    }

    /**
     * Détermine si l'utilisateur peut supprimer un concept.
     */
    public function delete(User $user, EntrevueConcept $concept): Response
    {
        return $this->update($user, $concept);
    }

    /**
     * Détermine si l'utilisateur peut réordonner les concepts du projet.
     */
    public function reorder(User $user, ProjetRecherche $projet): Response
    {
        return $this->projets->contribuerAuContenu($user, $projet);
    }
}
