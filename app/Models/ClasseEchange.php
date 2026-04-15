<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClasseEchange extends Model
{
    protected $table = 'classe_echanges';

    protected $fillable = [
        'classe_id',
        'auteur_id',
        'contenu',
    ];

    /**
     * Retourne la classe à laquelle appartient cet échange.
     */
    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    /**
     * Retourne l'auteur du message.
     */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }
}
