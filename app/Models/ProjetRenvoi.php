<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjetRenvoi extends Model
{
    protected $table = 'projet_renvois';

    protected $fillable = [
        'projet_id',
        'numero',
        'contenu',
        'type_reference',
        'champs_reference',
    ];

    /**
     * Sérialise champs_reference comme tableau PHP natif.
     */
    protected function casts(): array
    {
        return [
            'champs_reference' => 'array',
        ];
    }

    /**
     * Retourne le projet de recherche auquel appartient ce renvoi.
     */
    public function projet(): BelongsTo
    {
        return $this->belongsTo(ProjetRecherche::class, 'projet_id');
    }

    /**
     * Retourne les commentaires de l'enseignant sur ce renvoi, du plus ancien au plus récent.
     */
    public function commentaires(): HasMany
    {
        return $this->hasMany(ProjetRenvoiCommentaire::class, 'renvoi_id')->orderBy('created_at');
    }
}
