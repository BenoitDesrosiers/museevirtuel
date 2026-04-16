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
        'numero',
        'code',
        'nom',
        'jour_semaine',
        'plage_horaire',
    ];

    /**
     * Aligne automatiquement le code avec le cours et génère le numéro si absent.
     */
    protected static function booted(): void
    {
        static::creating(function (self $classe) {
            $coursCode = Cours::query()->whereKey($classe->cours_id)->value('code');
            if ($coursCode !== null) {
                $classe->code = $coursCode;
            }

            if (empty($classe->numero)) {
                $n = static::query()->where('cours_id', $classe->cours_id)->count() + 1;
                $classe->numero = str_pad((string) $n, 5, '0', STR_PAD_LEFT);
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
