<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GroupeNote extends Model
{
    protected $table = 'groupe_notes';

    protected $fillable = [
        'groupe_id',
        'user_id',
        'contenu',
    ];

    /**
     * Retourne l'auteur de la note.
     */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Retourne le groupe auquel appartient cette note.
     */
    public function groupe(): BelongsTo
    {
        return $this->belongsTo(Groupe::class);
    }

    /**
     * Retourne les corrections inline ajoutées par l'enseignant sur cette note.
     */
    public function corrections(): HasMany
    {
        return $this->hasMany(GroupeNoteCorrection::class, 'note_id');
    }
}
