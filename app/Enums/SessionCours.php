<?php

namespace App\Enums;

enum SessionCours: string
{
    case Hiver = 'hiver';
    case Ete = 'ete';
    case Automne = 'automne';

    /**
     * Retourne le libellé français de la session.
     */
    public function label(): string
    {
        return match ($this) {
            self::Hiver => 'Hiver',
            self::Ete => 'Été',
            self::Automne => 'Automne',
        };
    }
}
