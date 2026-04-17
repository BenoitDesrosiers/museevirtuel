<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Etablissement extends Model
{
    protected $fillable = [
        'nom',
        'ville',
        'code',
    ];

    /**
     * Enseignants appartenant à cet établissement.
     */
    public function enseignants(): HasMany
    {
        return $this->hasMany(User::class)->where('role', 'enseignant');
    }

    /**
     * Personnes âgées ayant choisi cet établissement lors de l'inscription.
     */
    public function temoins(): HasMany
    {
        return $this->hasMany(User::class)->where('role', 'personne_agee');
    }

    /**
     * Thématiques rattachées à cet établissement.
     */
    public function thematiques(): HasMany
    {
        return $this->hasMany(Thematique::class);
    }

    /**
     * Scope : établissements ayant au moins un enseignant.
     */
    public function scopeAvecEnseignants(Builder $query): Builder
    {
        return $query->whereHas('enseignants');
    }
}
