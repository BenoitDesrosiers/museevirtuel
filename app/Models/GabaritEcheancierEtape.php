<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Étape d'échéancier appartenant à un gabarit de cours.
 *
 * @property int $id
 * @property int $gabarit_cours_id
 * @property int $semaine Numéro de semaine (1–15)
 * @property string $etape Description de l'étape
 * @property int $ordre Ordre dans la semaine
 */
class GabaritEcheancierEtape extends Model
{
    protected $table = 'gabarit_echeancier_etapes';

    protected $fillable = [
        'gabarit_cours_id',
        'semaine',
        'etape',
        'ordre',
    ];

    /**
     * Gabarit auquel appartient cette étape.
     */
    public function gabaritCours(): BelongsTo
    {
        return $this->belongsTo(GabaritCours::class);
    }
}
