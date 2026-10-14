<?php

namespace App\Enums;

enum StatutEtudiantCours: string
{
    case Actif = 'actif';
    case Inactif = 'inactif';
    case Suspendu = 'suspendu';

    /**
     * Retourne le libellé français du statut.
     */
    public function label(): string
    {
        return match ($this) {
            self::Actif => 'Actif',
            self::Inactif => 'Inactif',
            self::Suspendu => 'Suspendu',
        };
    }
}
