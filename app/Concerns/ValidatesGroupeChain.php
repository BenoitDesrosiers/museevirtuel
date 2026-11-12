<?php

namespace App\Concerns;

use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\TypeProjet;
use App\Models\TypeProjetSection;
use Illuminate\Database\Eloquent\Model;

trait ValidatesGroupeChain
{
    /**
     * Vérifie que les paramètres de route forment une chaîne cohérente :
     * cours → classe → groupe → [ressource optionnelle].
     *
     * Interrompt avec 404 si un maillon de la chaîne ne correspond pas.
     * La ressource optionnelle doit posséder un attribut `groupe_id`.
     */
    protected function verifierChaine(
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        ?Model $ressource = null,
    ): void {
        abort_if($classe->cours_id !== $cours->id, 404);
        abort_if($groupe->classe_id !== $classe->id, 404);

        if ($ressource !== null) {
            abort_if($ressource->groupe_id !== $groupe->id, 404);
        }
    }

    /**
     * Vérifie la chaîne cours → classe → groupe et que le type de projet
     * appartient au même cours.
     *
     * Interrompt avec 404 si un maillon ne correspond pas.
     */
    protected function verifierChaineProjet(
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
    ): void {
        $this->verifierChaine($cours, $classe, $groupe);
        abort_if($typeProjet->cours_id !== $cours->id, 404);
    }

    /**
     * Vérifie la chaîne complète jusqu'à une section du type de projet.
     *
     * Interrompt avec 404 si un maillon ne correspond pas.
     */
    protected function verifierChaineSection(
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
        TypeProjetSection $section,
    ): void {
        $this->verifierChaineProjet($cours, $classe, $groupe, $typeProjet);
        abort_if($section->type_projet_id !== $typeProjet->id, 404);
    }
}
