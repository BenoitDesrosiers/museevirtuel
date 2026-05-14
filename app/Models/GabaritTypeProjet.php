<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Type de projet appartenant à un gabarit de cours.
 *
 * @property int $id
 * @property int $gabarit_cours_id
 * @property string $nom
 * @property string|null $description
 * @property float|null $ponderation
 * @property bool $is_sommatif
 * @property bool $generer_page_titre
 * @property bool $generer_table_matieres
 * @property bool $aide_reference
 * @property int $ordre
 */
class GabaritTypeProjet extends Model
{
    protected $table = 'gabarit_types_projets';

    protected $fillable = [
        'gabarit_cours_id',
        'nom',
        'description',
        'ponderation',
        'is_sommatif',
        'generer_page_titre',
        'generer_table_matieres',
        'aide_reference',
        'ordre',
    ];

    protected function casts(): array
    {
        return [
            'ponderation' => 'float',
            'is_sommatif' => 'boolean',
            'generer_page_titre' => 'boolean',
            'generer_table_matieres' => 'boolean',
            'aide_reference' => 'boolean',
        ];
    }

    /**
     * Gabarit auquel appartient ce type de projet.
     */
    public function gabaritCours(): BelongsTo
    {
        return $this->belongsTo(GabaritCours::class);
    }

    /**
     * Sections de ce type de projet dans le gabarit.
     */
    public function sections(): HasMany
    {
        return $this->hasMany(GabaritTypeProjetSection::class, 'gabarit_type_projet_id')->orderBy('ordre');
    }
}
