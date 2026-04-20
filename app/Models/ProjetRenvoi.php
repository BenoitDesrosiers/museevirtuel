<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjetRenvoi extends Model
{
    protected $table = 'projet_renvois';

    protected $fillable = [
        'projet_id',
        'numero',
        'contenu',
    ];

    /**
     * Retourne le projet de recherche auquel appartient ce renvoi.
     */
    public function projet(): BelongsTo
    {
        return $this->belongsTo(ProjetRecherche::class, 'projet_id');
    }
}
