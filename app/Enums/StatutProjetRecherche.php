<?php

namespace App\Enums;

use Carbon\CarbonInterface;

enum StatutProjetRecherche: string
{
    case EnCours = 'en_cours';
    case Remis = 'remis';
    case EnRetard = 'en_retard';
    case RemisEnRetard = 'remis_en_retard';

    /**
     * Retourne le libellé français affiché dans l'interface.
     */
    public function label(): string
    {
        return match ($this) {
            self::EnCours => 'En cours',
            self::Remis => 'Remis',
            self::EnRetard => 'En retard',
            self::RemisEnRetard => 'Remis en retard',
        };
    }

    /**
     * Détermine le statut à partir de la date limite et de la date de remise.
     */
    public static function fromDates(
        ?CarbonInterface $dateRemise,
        ?CarbonInterface $remisLe,
        ?CarbonInterface $maintenant = null,
    ): self {
        if ($remisLe !== null) {
            return $dateRemise !== null && $remisLe->gt($dateRemise)
                ? self::RemisEnRetard
                : self::Remis;
        }

        return $dateRemise !== null
            && ($maintenant ?? now())->gt($dateRemise)
            ? self::EnRetard
            : self::EnCours;
    }
}
