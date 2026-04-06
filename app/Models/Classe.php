<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Classe extends Model
{
    protected $fillable = [
        'nom_cours',
        'description',
        'code',
        'groupe',
        'enseignant_id',
    ];

    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enseignant_id');
    }

    public function etudiants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'classe_etudiant')
            ->withPivot(['statut_cours'])
            ->withTimestamps();
    }

    public function groupes(): HasMany
    {
        return $this->hasMany(Groupe::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ClasseDocument::class)->orderByDesc('created_at');
    }

    /**
     * Retourne les étapes de l'échéancier de la classe, triées par semaine puis par ordre.
     */
    public function echeancierEtapes(): HasMany
    {
        return $this->hasMany(EcheancierEtape::class)->orderBy('semaine')->orderBy('ordre');
    }
}
