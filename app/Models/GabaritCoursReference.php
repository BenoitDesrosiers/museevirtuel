<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GabaritCoursReference extends Model
{
    protected $fillable = [
        'gabarit_cours_id',
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
     * Retourne le gabarit de cours auquel appartient cette référence.
     */
    public function gabaritCours(): BelongsTo
    {
        return $this->belongsTo(GabaritCours::class);
    }
}
