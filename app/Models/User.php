<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'prenom',
        'nom',
        'email',
        'no_da',
        'password',
        'role',
        'locale',
        'statut',
        'approuve_par_id',
        'description',
        'provenance',
        'thematique_id',
        'theme_libre',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = ['name'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Accessor pour la compatibilité avec les composants existants
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->prenom.' '.$this->nom,
        );
    }

    // Email toujours en minuscules
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => strtolower($value),
        );
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isEnseignant(): bool
    {
        return $this->role === 'enseignant';
    }

    public function isEtudiant(): bool
    {
        return $this->role === 'etudiant';
    }

    public function isPersonneAgee(): bool
    {
        return $this->role === 'personne_agee';
    }

    public function estEnAttente(): bool
    {
        return $this->statut === 'en_attente';
    }

    public function estRefuse(): bool
    {
        return $this->statut === 'refuse';
    }

    /**
     * Filtre les utilisateurs dont le compte est en attente d'approbation.
     */
    public function scopeEnAttente(Builder $query): Builder
    {
        return $query->where('statut', 'en_attente');
    }

    /**
     * Filtre les utilisateurs dont la demande a été refusée.
     */
    public function scopeRefuse(Builder $query): Builder
    {
        return $query->where('statut', 'refuse');
    }

    /**
     * Filtre les utilisateurs dont le compte est actif.
     */
    public function scopeActif(Builder $query): Builder
    {
        return $query->where('statut', 'actif');
    }

    // Classes créées par l'enseignant
    public function classes(): HasMany
    {
        return $this->hasMany(Classe::class, 'enseignant_id');
    }

    // Classes dans lesquelles l'étudiant est inscrit
    public function classesInscrites(): BelongsToMany
    {
        return $this->belongsToMany(Classe::class, 'classe_etudiant')
            ->withPivot(['statut_cours'])
            ->withTimestamps();
    }

    // Thématiques créées par l'enseignant
    public function thematiques(): HasMany
    {
        return $this->hasMany(Thematique::class, 'enseignant_id');
    }

    // Groupes dont l'étudiant est membre
    public function groupesMembre(): BelongsToMany
    {
        return $this->belongsToMany(Groupe::class, 'groupe_etudiant');
    }

    // Thématique choisie lors de l'inscription (personne âgée)
    public function thematique(): BelongsTo
    {
        return $this->belongsTo(Thematique::class);
    }

    // Thématiques choisies par la personne âgée (pivot many-to-many)
    public function thematiquesChoisies(): BelongsToMany
    {
        return $this->belongsToMany(Thematique::class, 'user_thematique');
    }

    // Enseignant ayant approuvé ce compte personne âgée
    public function approuveParEnseignant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approuve_par_id');
    }
}
