<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClasseNoteCorrection extends Model
{
    protected $table = 'classe_note_corrections';

    protected $fillable = [
        'note_id',
        'commentaire_id',
        'contenu',
        'user_id',
    ];

    /**
     * Retourne la note à laquelle cette correction est rattachée.
     */
    public function note(): BelongsTo
    {
        return $this->belongsTo(ClasseNote::class, 'note_id');
    }

    /**
     * Retourne l'enseignant auteur de la correction.
     */
    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
