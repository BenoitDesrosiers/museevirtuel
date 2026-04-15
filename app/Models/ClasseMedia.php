<?php

namespace App\Models;

use App\Concerns\HasPublicFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClasseMedia extends Model
{
    use HasPublicFile;

    protected $table = 'classe_medias';

    protected $fillable = [
        'classe_id',
        'user_id',
        'nom_original',
        'file_path',
        'type',
        'taille',
    ];

    protected $appends = ['url'];

    public function getUrlAttribute(): string
    {
        return asset($this->file_path);
    }

    /**
     * Retourne la classe à laquelle appartient ce média.
     */
    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    /**
     * Retourne l'auteur du média.
     */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
