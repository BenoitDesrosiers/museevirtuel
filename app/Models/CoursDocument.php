<?php

namespace App\Models;

use App\Concerns\HasPublicFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoursDocument extends Model
{
    use HasPublicFile;

    protected $table = 'cours_documents';

    protected $fillable = [
        'cours_id',
        'enseignant_id',
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
     * Retourne le cours auquel appartient ce document.
     */
    public function cours(): BelongsTo
    {
        return $this->belongsTo(Cours::class);
    }

    /**
     * Retourne l'enseignant qui a déposé le document.
     */
    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enseignant_id');
    }
}
