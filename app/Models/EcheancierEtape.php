<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EcheancierEtape extends Model
{
    protected $fillable = [
        'cours_id',
        'semaine',
        'etape',
        'is_done',
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
            'is_done' => 'boolean',
            'semaine' => 'integer',
            'ordre' => 'integer',
        ];
    }

    /**
     * Retourne le cours auquel appartient cette étape.
     */
    public function cours(): BelongsTo
    {
        return $this->belongsTo(Cours::class);
    }

    /**
     * Retourne les progressions individuelles des étudiants pour cette étape.
     */
    public function etudiantProgress(): HasMany
    {
        return $this->hasMany(EcheancierEtudiantProgress::class, 'echeancier_etape_id');
    }
}
