<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoursReference extends Model
{
    protected $fillable = [
        'cours_id',
        'nom',
        'url',
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
     * Retourne le cours auquel appartient cette référence.
     */
    public function cours(): BelongsTo
    {
        return $this->belongsTo(Cours::class);
    }
}
