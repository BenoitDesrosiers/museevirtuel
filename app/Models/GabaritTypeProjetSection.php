<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Section d'un type de projet dans un gabarit de cours.
 *
 * @property int $id
 * @property int $gabarit_type_projet_id
 * @property string $label
 * @property string $type texte, paragraphes, individuel, entrevue…
 * @property int $ordre
 */
class GabaritTypeProjetSection extends Model
{
    protected $table = 'gabarit_types_projets_sections';

    protected $fillable = [
        'gabarit_type_projet_id',
        'label',
        'type',
        'ordre',
    ];

    /**
     * Type de projet du gabarit auquel appartient cette section.
     */
    public function gabaritTypeProjet(): BelongsTo
    {
        return $this->belongsTo(GabaritTypeProjet::class, 'gabarit_type_projet_id');
    }
}
