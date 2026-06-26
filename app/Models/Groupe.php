<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Groupe extends Model
{
    protected $table = 'groupes';

    protected $fillable = [
        'classe_id',
        'code',
        'created_by',
        'personne_agee_id',
    ];

    protected $appends = ['numero'];

    /**
     * Génère automatiquement un code unique à la création si non fourni.
     */
    protected static function booted(): void
    {
        static::creating(function (self $groupe) {
            if (empty($groupe->code)) {
                $n = static::where('classe_id', $groupe->classe_id)->count() + 1;
                $groupe->code = sprintf('GR-%d-%02d', $groupe->classe_id, $n);
            }
        });
    }

    /**
     * Retourne le numéro d'ordre du groupe au sein de sa classe (1, 2, 3…).
     */
    public function getNumeroAttribute(): int
    {
        return static::where('classe_id', $this->classe_id)
            ->where('id', '<=', $this->id)
            ->count();
    }

    /**
     * Retourne la classe (section) à laquelle appartient ce groupe.
     */
    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    /**
     * Retourne l'utilisateur qui a créé le groupe.
     */
    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Retourne la personne âgée (témoin) associée au groupe.
     */
    public function temoin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'personne_agee_id');
    }

    /**
     * Retourne les échanges du groupe, triés par date.
     */
    public function echanges(): HasMany
    {
        return $this->hasMany(GroupeEchange::class)->orderBy('created_at');
    }

    /**
     * Retourne les membres étudiants du groupe.
     */
    public function membres(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'groupe_etudiant');
    }

    /**
     * Retourne les thématiques choisies par le groupe.
     */
    public function thematiques(): BelongsToMany
    {
        return $this->belongsToMany(Thematique::class, 'groupe_thematique');
    }

    /**
     * Retourne les notes du groupe, triées par date.
     */
    public function notes(): HasMany
    {
        return $this->hasMany(GroupeNote::class)->orderBy('created_at');
    }

    /**
     * Retourne les médias du groupe, triés du plus récent au plus ancien.
     */
    public function medias(): HasMany
    {
        return $this->hasMany(GroupeMedia::class)->orderByDesc('created_at');
    }

    /**
     * Retourne les projets de recherche du groupe.
     */
    public function projets(): HasMany
    {
        return $this->hasMany(ProjetRecherche::class);
    }

    /**
     * Retourne les vidéos publiées du groupe, triées du plus récent au plus ancien.
     */
    public function videos(): HasMany
    {
        return $this->hasMany(GroupeVideo::class)->orderByDesc('created_at');
    }
}
