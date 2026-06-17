<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TypeProjetCritere extends Model
{
    protected $table = 'type_projet_criteres';

    protected $fillable = [
        'type_projet_id',
        'section_id',
        'type',
        'contenu_type',
        'pointage',
        'contenu',
        'echelle',
        'visible',
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
            'echelle' => 'array',
            'visible' => 'boolean',
            'pointage' => 'decimal:2',
        ];
    }

    /**
     * Indique si ce critère est global (non lié à une section spécifique).
     *
     * Les critères globaux sont affichés avant les sections dans la vue de correction.
     */
    public function estGlobal(): bool
    {
        return $this->section_id === null;
    }

    /**
     * Retourne le type de projet auquel appartient ce critère.
     */
    public function typeProjet(): BelongsTo
    {
        return $this->belongsTo(TypeProjet::class, 'type_projet_id');
    }

    /**
     * Retourne la section à laquelle appartient ce critère, ou null s'il est global.
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(TypeProjetSection::class, 'section_id');
    }

    /**
     * Retourne toutes les corrections appliquées sur ce critère, tous projets confondus.
     */
    public function corrections(): HasMany
    {
        return $this->hasMany(ProjetCritereCorrection::class, 'critere_id');
    }
}
