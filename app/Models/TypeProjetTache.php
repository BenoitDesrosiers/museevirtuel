<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TypeProjetTache extends Model
{
    protected $table = 'type_projet_taches';

    protected $fillable = [
        'type_projet_id',
        'titre',
        'description',
        'ordre',
    ];

    /**
     * Retourne le type de projet auquel appartient cette tâche.
     */
    public function typeProjet(): BelongsTo
    {
        return $this->belongsTo(TypeProjet::class, 'type_projet_id');
    }

    /**
     * Retourne les entrées de suivi par groupe pour cette tâche.
     */
    public function groupeTaches(): HasMany
    {
        return $this->hasMany(GroupeTache::class, 'tache_id');
    }
}
