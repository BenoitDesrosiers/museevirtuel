<?php

namespace App\Policies;

use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\User;
use App\Models\VisioConference;

/**
 * Règles d'accès aux visioconférences d'un cours.
 *
 * Deux niveaux :
 * - gérer (modifier, supprimer, déposer l'enregistrement) : enseignant du cours ou admin,
 *   comme CoursPolicy::update ;
 * - participer (démarrer, terminer, voir l'enregistrement) : en plus, les étudiants.
 *   Une visio ciblée sur un groupe n'est ouverte qu'aux membres de ce groupe ;
 *   une visio sans groupe est ouverte aux membres de n'importe quel groupe du cours.
 */
readonly class VisioConferencePolicy
{
    /**
     * Injecte la policy du cours, qui définit qui gère le cours.
     */
    public function __construct(private CoursPolicy $coursPolicy) {}

    /**
     * Détermine si l'utilisateur peut créer une visio dans le cours.
     *
     * Sans groupe ciblé : réservé à l'enseignant du cours. Avec un groupe ciblé
     * (déjà vérifié comme appartenant au cours) : aussi ouvert aux membres de ce groupe.
     */
    public function create(User $user, Cours $cours, ?Groupe $groupe = null): bool
    {
        if ($this->estEnseignantDuCours($user, $cours)) {
            return true;
        }

        return $groupe !== null && $this->estMembreDuGroupe($user, $groupe);
    }

    /**
     * Détermine si l'utilisateur peut modifier la visio ou y déposer un enregistrement.
     */
    public function update(User $user, VisioConference $visio): bool
    {
        return $this->coursPolicy->update($user, $visio->cours);
    }

    /**
     * Détermine si l'utilisateur peut supprimer la visio.
     */
    public function delete(User $user, VisioConference $visio): bool
    {
        return $this->update($user, $visio);
    }

    /**
     * Détermine si l'utilisateur peut démarrer, terminer ou regarder la visio.
     */
    public function participer(User $user, VisioConference $visio): bool
    {
        // Il faudra retirer la portion $user->isEnseignant() && en production
        if ($this->estEnseignantDuCours($user, $visio->cours)) {
            return true;
        }

        if ($visio->groupe_id !== null) {
            return $this->estMembreDuGroupe($user, $visio->groupe);
        }

        return Classe::where('cours_id', $visio->cours_id)
            ->whereHas('groupes.membres', fn ($q) => $q->where('users.id', $user->id))
            ->exists();
    }

    /**
     * Vérifie si l'utilisateur est l'enseignant du cours ou un admin.
     */
    private function estEnseignantDuCours(User $user, Cours $cours): bool
    {
        return $this->coursPolicy->update($user, $cours);
    }

    /**
     * Vérifie si l'utilisateur est membre du groupe.
     */
    private function estMembreDuGroupe(User $user, Groupe $groupe): bool
    {
        return $groupe->membres()->where('users.id', $user->id)->exists();
    }
}
