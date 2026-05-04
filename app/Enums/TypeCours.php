<?php

namespace App\Enums;

enum TypeCours: string
{
    case Dep = 'dep';
    case CoursComplementaire = 'cours_complementaire';
    case CoursComplet = 'cours_complet';

    /**
     * Retourne le libellé français du type de cours.
     */
    public function label(): string
    {
        return match ($this) {
            self::Dep => 'DEP',
            self::CoursComplementaire => 'Cours complémentaire',
            self::CoursComplet => 'Cours complet',
        };
    }
}
