<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EntrevueConcept extends Model
{
    protected $table = 'entrevue_concepts';

    protected $fillable = [
        'projet_id',
        'section_id',
        'label',
        'ordre',
    ];

    /**
     * Retourne le projet de recherche auquel appartient ce concept.
     */
    public function projet(): BelongsTo
    {
        return $this->belongsTo(ProjetRecherche::class, 'projet_id');
    }

    /**
     * Retourne la section de type projet à laquelle appartient ce concept.
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(TypeProjetSection::class, 'section_id');
    }

    /**
     * Retourne les lignes (dimension/indicateur/questions) du concept.
     */
    public function lignes(): HasMany
    {
        return $this->hasMany(EntrevueLigne::class, 'concept_id')->orderBy('ordre');
    }
}
