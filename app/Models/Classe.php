<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Classe extends Model
{
    protected $table = 'classes';

    protected $fillable = [
        'cours_id',
        'code',
        'nom',
    ];

    /**
     * Génère automatiquement un code unique à la création si non fourni.
     */
    protected static function booted(): void
    {
        static::creating(function (self $classe) {
            if (empty($classe->code)) {
                $n = static::where('cours_id', $classe->cours_id)->count() + 1;
                $classe->code = sprintf('CL-%d-%02d', $classe->cours_id, $n);
            }
        });
    }

    /**
     * Retourne le cours auquel appartient cette classe (section).
     */
    public function cours(): BelongsTo
    {
        return $this->belongsTo(Cours::class);
    }

    /**
     * Retourne les groupes d'étudiants dans cette classe.
     */
    public function groupes(): HasMany
    {
        return $this->hasMany(Groupe::class);
    }

    /**
     * Retourne les étudiants inscrits dans cette classe.
     */
    public function etudiants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'classe_etudiant')
            ->withPivot(['statut_cours'])
            ->withTimestamps();
    }
}
