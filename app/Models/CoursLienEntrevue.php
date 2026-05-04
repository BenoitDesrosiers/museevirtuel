<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoursLienEntrevue extends Model
{
    protected $table = 'cours_liens_entrevue';

    protected $fillable = [
        'cours_id',
        'label',
        'url',
        'ordre',
    ];

    /**
     * Retourne le cours auquel appartient ce lien d'entrevue.
     */
    public function cours(): BelongsTo
    {
        return $this->belongsTo(Cours::class, 'cours_id');
    }
}
