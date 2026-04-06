<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjetSectionContenu extends Model
{
    protected $table = 'projet_section_contenus';

    protected $fillable = [
        'projet_id',
        'section_id',
        'contenu',
    ];

    /**
     * Retourne le projet de recherche auquel appartient ce contenu.
     */
    public function projet(): BelongsTo
    {
        return $this->belongsTo(ProjetRecherche::class, 'projet_id');
    }

    /**
     * Retourne la section de type de projet associée.
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(TypeProjetSection::class, 'section_id');
    }
}
