<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Gabarit système pour un type de cours.
 *
 * Contient les objectifs, types de projets et étapes d'échéancier
 * qui seront clonés vers un nouveau cours lors de la création avec gabarit.
 *
 * @property int $id
 * @property string $slug Identifiant stable (ex: 'cours_complet')
 * @property string $type_cours Valeur de l'enum TypeCours
 * @property string $nom Nom lisible du gabarit
 */
class GabaritCours extends Model
{
    protected $table = 'gabarit_cours';

    protected $fillable = [
        'slug',
        'type_cours',
        'nom',
    ];

    /**
     * Objectifs pédagogiques du gabarit.
     */
    public function objectifs(): HasMany
    {
        return $this->hasMany(GabaritCoursObjectif::class)->orderBy('ordre');
    }

    /**
     * Types de projets du gabarit, avec leurs sections.
     */
    public function typesProjets(): HasMany
    {
        return $this->hasMany(GabaritTypeProjet::class)->orderBy('ordre');
    }

    /**
     * Étapes d'échéancier du gabarit.
     */
    public function echeancierEtapes(): HasMany
    {
        return $this->hasMany(GabaritEcheancierEtape::class)->orderBy('semaine')->orderBy('ordre');
    }
}
