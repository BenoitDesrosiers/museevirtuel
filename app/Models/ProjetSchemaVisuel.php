<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjetSchemaVisuel extends Model
{
    protected $table = 'projet_schema_visuels';

    protected $fillable = [
        'projet_id',
        'section_id',
        'contenu',
    ];

    /**
     * Retourne les casts de colonnes pour l'hydratation automatique.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'contenu' => 'array',
        ];
    }

    /**
     * Retourne le projet de recherche auquel appartient ce schéma.
     */
    public function projet(): BelongsTo
    {
        return $this->belongsTo(ProjetRecherche::class, 'projet_id');
    }

    /**
     * Retourne la section de TypeProjet à laquelle appartient ce schéma.
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(TypeProjetSection::class, 'section_id');
    }

    /**
     * Retourne le contenu par défaut (zones vides, pas d'image centrale).
     *
     * @return array<string, mixed>
     */
    public static function contenuVide(): array
    {
        return [
            'image_centrale' => null,
            'zones' => [
                'causes' => [],
                'activites' => [],
                'consequences' => [],
            ],
        ];
    }
}
