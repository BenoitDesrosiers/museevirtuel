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
        'type',
        'position',
        'mot_annote',
        'user_id',
        'cible_user_id',
        'points_malus',
    ];

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

    /**
     * Retourne l'étudiant visé par la déduction de points (annotations de correction uniquement).
     */
    public function cible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cible_user_id');
    }
}
