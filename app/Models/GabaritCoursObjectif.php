<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Objectif pédagogique appartenant à un gabarit de cours.
 *
 * @property int $id
 * @property int $gabarit_cours_id
 * @property string $contenu
 * @property int $ordre
 */
class GabaritCoursObjectif extends Model
{
    protected $table = 'gabarit_cours_objectifs';

    protected $fillable = [
        'gabarit_cours_id',
        'contenu',
        'ordre',
    ];

    /**
     * Gabarit auquel appartient cet objectif.
     */
    public function gabaritCours(): BelongsTo
    {
        return $this->belongsTo(GabaritCours::class);
    }
}
