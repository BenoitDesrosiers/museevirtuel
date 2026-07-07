<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Critère de correction appartenant à un type de projet d'un gabarit de cours.
 *
 * Copié vers TypeProjetCritere lors de l'application du gabarit à un nouveau cours.
 *
 * @property int $id
 * @property int $gabarit_type_projet_id
 * @property int|null $gabarit_section_id null = critère global (avant toutes les sections)
 * @property string $type 'positif' | 'negatif'
 * @property string $contenu_type 'texte' | 'echelle'
 * @property float $pointage
 * @property string|null $contenu HTML libre
 * @property string|null $note Note interne, visible uniquement par l'enseignant
 * @property array|null $echelle [{label, points, description?}, ...]
 * @property bool $visible
 * @property int $ordre
 */
class GabaritTypeProjetCritere extends Model
{
    protected $table = 'gabarit_type_projet_criteres';

    protected $fillable = [
        'gabarit_type_projet_id',
        'gabarit_section_id',
        'type',
        'contenu_type',
        'pointage',
        'contenu',
        'note',
        'echelle',
        'visible',
        'ordre',
    ];

    protected function casts(): array
    {
        return [
            'pointage' => 'decimal:2',
            'echelle' => 'array',
            'visible' => 'boolean',
        ];
    }

    /**
     * Type de projet du gabarit auquel appartient ce critère.
     */
    public function gabaritTypeProjet(): BelongsTo
    {
        return $this->belongsTo(GabaritTypeProjet::class, 'gabarit_type_projet_id');
    }

    /**
     * Section du gabarit à laquelle ce critère est rattaché (null = global).
     */
    public function gabaritSection(): BelongsTo
    {
        return $this->belongsTo(GabaritTypeProjetSection::class, 'gabarit_section_id');
    }
}
