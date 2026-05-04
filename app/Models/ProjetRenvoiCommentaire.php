<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjetRenvoiCommentaire extends Model
{
    protected $table = 'projet_renvoi_commentaires';

    protected $fillable = [
        'renvoi_id',
        'user_id',
        'contenu',
    ];

    /**
     * Retourne le renvoi (endnote) auquel appartient ce commentaire.
     */
    public function renvoi(): BelongsTo
    {
        return $this->belongsTo(ProjetRenvoi::class, 'renvoi_id');
    }

    /**
     * Retourne l'enseignant auteur du commentaire.
     */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
