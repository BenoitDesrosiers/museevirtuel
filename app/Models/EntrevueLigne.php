<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntrevueLigne extends Model
{
    protected $table = 'entrevue_lignes';

    protected $fillable = [
        'concept_id',
        'dimension',
        'indicateur',
        'questions',
        'ordre',
    ];

    /**
     * Retourne le concept auquel appartient cette ligne.
     */
    public function concept(): BelongsTo
    {
        return $this->belongsTo(EntrevueConcept::class, 'concept_id');
    }

    /**
     * Caste la colonne questions en tableau PHP.
     */
    protected function casts(): array
    {
        return [
            'questions' => 'array',
        ];
    }
}
