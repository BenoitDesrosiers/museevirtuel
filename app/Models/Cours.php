<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cours extends Model
{
    protected $table = 'cours';

    protected $fillable = [
        'nom_cours',
        'description',
        'code',
        'groupe',
        'enseignant_id',
    ];

    /**
     * Retourne l'enseignant responsable du cours.
     */
    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enseignant_id');
    }

    /**
     * Retourne les classes (sections) de ce cours.
     */
    public function classes(): HasMany
    {
        return $this->hasMany(Classe::class);
    }

    /**
     * Retourne les documents pédagogiques du cours.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(CoursDocument::class)->orderByDesc('created_at');
    }

    /**
     * Retourne les étapes de l'échéancier, triées par semaine puis par ordre.
     */
    public function echeancierEtapes(): HasMany
    {
        return $this->hasMany(EcheancierEtape::class)->orderBy('semaine')->orderBy('ordre');
    }
}
