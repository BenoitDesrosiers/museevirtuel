<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupeEchange extends Model
{
    protected $table = 'groupe_echanges';

    protected $fillable = [
        'groupe_id',
        'auteur_id',
        'contenu',
    ];

    /**
     * Retourne le groupe auquel appartient cet échange.
     */
    public function groupe(): BelongsTo
    {
        return $this->belongsTo(Groupe::class);
    }

    /**
     * Retourne l'auteur du message.
     */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }
}
