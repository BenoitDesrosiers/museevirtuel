<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjetAnnotation extends Model
{
    protected $fillable = [
        'projet_id',
        'champ',
        'commentaire_id',
        'contenu',
        'position',
        'mot_annote',
        'points_malus',
        'cible_user_id',
        'annotation_type',
        'user_id',
    ];

    /**
     * Retourne les casts de colonnes pour l'hydratation automatique.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'points_malus' => 'decimal:2',
        ];
    }

    /**
     * Retourne le projet de recherche auquel appartient cette annotation.
     */
    public function projet(): BelongsTo
    {
        return $this->belongsTo(ProjetRecherche::class, 'projet_id');
    }

    /**
     * Retourne l'enseignant auteur de l'annotation.
     */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
