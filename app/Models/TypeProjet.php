<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TypeProjet extends Model
{
    protected $table = 'types_projets';

    protected $fillable = [
        'enseignant_id',
        'cours_id',
        'nom',
        'description',
        'accessible',
        'date_remise',
        'remises_multiples',
        'retard_permis',
        'generer_page_titre',
        'generer_table_matieres',
        'aide_reference',
        'ponderation',
        'is_sommatif',
    ];

    protected function casts(): array
    {
        return [
            'accessible' => 'boolean',
            'date_remise' => 'datetime',
            'remises_multiples' => 'boolean',
            'retard_permis' => 'boolean',
            'generer_page_titre' => 'boolean',
            'generer_table_matieres' => 'boolean',
            'aide_reference' => 'boolean',
            'ponderation' => 'decimal:2',
            'is_sommatif' => 'boolean',
        ];
    }

    /**
     * Retourne l'enseignant propriétaire de ce type de projet.
     */
    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enseignant_id');
    }

    /**
     * Retourne le cours auquel ce type de projet est rattaché.
     */
    public function cours(): BelongsTo
    {
        return $this->belongsTo(Cours::class);
    }

    /**
     * Retourne les sections définies par le professeur pour ce type de projet, triées par ordre.
     */
    public function sections(): HasMany
    {
        return $this->hasMany(TypeProjetSection::class, 'type_projet_id')->orderBy('ordre');
    }

    /**
     * Retourne les projets de recherche utilisant ce type.
     */
    public function projets(): HasMany
    {
        return $this->hasMany(ProjetRecherche::class, 'type_projet_id');
    }

    /**
     * Retourne les tâches définies par l'enseignant pour ce type de projet, triées par ordre.
     */
    public function taches(): HasMany
    {
        return $this->hasMany(TypeProjetTache::class, 'type_projet_id')->orderBy('ordre');
    }

    /**
     * Retourne tous les critères de correction (globaux et par section), triés par ordre.
     */
    public function criteres(): HasMany
    {
        return $this->hasMany(TypeProjetCritere::class, 'type_projet_id')->orderBy('ordre');
    }

    /**
     * Retourne uniquement les critères globaux (non liés à une section), triés par ordre.
     *
     * Ces critères sont affichés avant les sections dans la vue de correction.
     */
    public function criteresGlobaux(): HasMany
    {
        return $this->hasMany(TypeProjetCritere::class, 'type_projet_id')
            ->whereNull('section_id')
            ->orderBy('ordre');
    }
}
