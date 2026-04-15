<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClasseNote extends Model
{
    protected $table = 'classe_notes';

    protected $fillable = [
        'classe_id',
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
     * Retourne la classe à laquelle appartient cette note.
     */
    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    /**
     * Retourne les corrections inline ajoutées par l'enseignant sur cette note.
     */
    public function corrections(): HasMany
    {
        return $this->hasMany(ClasseNoteCorrection::class, 'note_id');
    }
}
