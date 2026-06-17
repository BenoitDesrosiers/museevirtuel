<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TypeProjetSection extends Model
{
    protected $table = 'type_projet_sections';

    protected $fillable = [
        'type_projet_id',
        'label',
        'description',
        'ordre',
        'type',
        'pointage',
    ];

    /**
     * Retourne les casts de colonnes pour l'hydratation automatique.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pointage' => 'decimal:2',
        ];
    }

    /**
     * Retourne le type de projet auquel appartient cette section.
     */
    public function typeProjet(): BelongsTo
    {
        return $this->belongsTo(TypeProjet::class, 'type_projet_id');
    }

    /**
     * Retourne les contenus enregistrés pour cette section dans les projets.
     */
    public function contenus(): HasMany
    {
        return $this->hasMany(ProjetSectionContenu::class, 'section_id');
    }

    /**
     * Retourne les paragraphes de section enregistrés pour cette section dans les projets.
     */
    public function sectionParagraphes(): HasMany
    {
        return $this->hasMany(ProjetSectionParagraphe::class, 'section_id');
    }

    /**
     * Retourne les questions de la banque définies par l'enseignant pour cette section.
     */
    public function questionsBanque(): HasMany
    {
        return $this->hasMany(QuestionBanque::class, 'section_id')->orderBy('ordre');
    }

    /**
     * Retourne les médias uploadés pour cette section dans les projets.
     */
    public function medias(): HasMany
    {
        return $this->hasMany(ProjetSectionMedia::class, 'section_id');
    }

    /**
     * Retourne les critères de correction définis pour cette section, triés par ordre.
     */
    public function criteres(): HasMany
    {
        return $this->hasMany(TypeProjetCritere::class, 'section_id')->orderBy('ordre');
    }
}
