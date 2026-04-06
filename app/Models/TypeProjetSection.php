<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TypeProjetSection extends Model
{
    protected $table = 'type_projet_sections';

    protected $fillable = [
        'type_projet_id',
        'label',
        'description',
        'ordre',
    ];

    /**
     * Retourne le type de projet auquel appartient cette section.
     */
    public function typeProjet(): BelongsTo
    {
        return $this->belongsTo(TypeProjet::class, 'type_projet_id');
    }

    /**
     * Retourne les contenus enregistrés pour cette section dans les projets.
     */
    public function contenus(): HasMany
    {
        return $this->hasMany(ProjetSectionContenu::class, 'section_id');
    }
}
