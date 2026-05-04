<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoursObjectif extends Model
{
    protected $fillable = [
        'cours_id',
        'contenu',
        'ordre',
    ];

    /**
     * Retourne les casts de colonnes pour l'hydratation automatique.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ordre' => 'integer',
        ];
    }

    /**
     * Retourne le cours auquel appartient cet objectif.
     */
    public function cours(): BelongsTo
    {
        return $this->belongsTo(Cours::class);
    }
}
