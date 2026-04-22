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
        'etablissement_id',
        'description',
        'provenance',
        'thematique_id',
        'theme_libre',
        'engagements_acceptes_le',
        'signature_electronique',
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
            'engagements_acceptes_le' => 'datetime',
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

    /**
     * Indique si la personne âgée a signé les engagements lors de son inscription.
     */
    public function aSigneLesEngagements(): bool
    {
        return ! is_null($this->engagements_acceptes_le);
    }

    /**
     * Retourne le ou les thèmes libres saisis par la personne âgée pour ses cégeps,
     * concaténés par une virgule. Requiert que la relation etablissementsChoisis soit chargée.
     */
    public function themeLibre(): ?string
    {
        return $this->etablissementsChoisis
            ->pluck('pivot.theme_libre')
            ->filter()
            ->join(', ') ?: null;
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

    /**
     * Cours auxquels l'étudiant est inscrit via ses sections (Classe).
     */
    public function coursInscrits(): Builder
    {
        return Cours::whereHas('classes', function (Builder $q): void {
            $q->whereHas('etudiants', fn (Builder $q2) => $q2->where('users.id', $this->id));
        });
    }

    // Cours créés par l'enseignant
    public function cours(): HasMany
    {
        return $this->hasMany(Cours::class, 'enseignant_id');
    }

    // Classes (sections) dans lesquelles l'étudiant est inscrit
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

    // Établissement auquel appartient l'enseignant ou qu'a choisi la personne âgée
    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class);
    }

    // Établissements choisis par la personne âgée lors de l'inscription (avec thème libre par cégep)
    public function etablissementsChoisis(): BelongsToMany
    {
        return $this->belongsToMany(Etablissement::class, 'user_etablissement')
            ->withPivot('theme_libre');
    }
}
