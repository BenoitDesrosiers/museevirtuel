<?php

namespace App\Models;

use App\Enums\SessionCours;
use App\Enums\TypeCours;
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
        'annee',
        'session',
        'is_verrouille',
        'enseignant_id',
        'type_cours',
        'taille_equipe_min',
        'taille_equipe_max',
    ];

    /**
     * Retourne les casts de colonnes pour l'hydratation automatique.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type_cours' => TypeCours::class,
            'session' => SessionCours::class,
            'annee' => 'integer',
            'is_verrouille' => 'boolean',
            'taille_equipe_min' => 'integer',
            'taille_equipe_max' => 'integer',
        ];
    }

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

    /**
     * Retourne les objectifs pédagogiques du cours, triés par ordre.
     */
    public function objectifs(): HasMany
    {
        return $this->hasMany(CoursObjectif::class)->orderBy('ordre');
    }

    /**
     * Retourne les types de projets associés à ce cours.
     */
    public function typesProjets(): HasMany
    {
        return $this->hasMany(TypeProjet::class);
    }

    /**
     * Retourne les liens d'entrevue partagés par l'enseignant pour ce cours, triés par ordre.
     */
    public function liensEntrevue(): HasMany
    {
        return $this->hasMany(CoursLienEntrevue::class)->orderBy('ordre');
    }

    /**
     * Retourne les visioconférences de ce cours, triées de la plus récente à la plus ancienne.
     */
    public function visioConferences(): HasMany
    {
        return $this->hasMany(VisioConference::class)->orderByDesc('created_at');
    }
}
