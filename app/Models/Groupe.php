<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Groupe extends Model
{
    protected $fillable = [
        'classe_id',
        'created_by',
        'personne_agee_id',
    ];

    protected $appends = ['numero'];

    /**
     * Retourne le numéro d'ordre du groupe au sein de sa classe (1, 2, 3…).
     */
    public function getNumeroAttribute(): int
    {
        return static::where('classe_id', $this->classe_id)
            ->where('id', '<=', $this->id)
            ->count();
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function temoin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'personne_agee_id');
    }

    public function echanges(): HasMany
    {
        return $this->hasMany(GroupeEchange::class)->orderBy('created_at');
    }

    public function membres(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'groupe_etudiant');
    }

    public function thematiques(): BelongsToMany
    {
        return $this->belongsToMany(Thematique::class, 'groupe_thematique');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(GroupeNote::class)->orderBy('created_at');
    }

    public function medias(): HasMany
    {
        return $this->hasMany(GroupeMedia::class)->orderByDesc('created_at');
    }

    /**
     * Retourne les projets de recherche individuels des membres du groupe.
     */
    public function projets(): HasMany
    {
        return $this->hasMany(ProjetRecherche::class);
    }
}
