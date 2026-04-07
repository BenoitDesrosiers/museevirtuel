<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjetSectionParagraphe extends Model
{
    protected $table = 'projet_section_paragraphes';

    protected $fillable = [
        'projet_id',
        'section_id',
        'ordre',
        'titre',
        'contenu',
    ];

    /**
     * Retourne le projet de recherche auquel appartient ce paragraphe.
     */
    public function projet(): BelongsTo
    {
        return $this->belongsTo(ProjetRecherche::class, 'projet_id');
    }

    /**
     * Retourne la section de type projet à laquelle appartient ce paragraphe.
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(TypeProjetSection::class, 'section_id');
    }
}
