<?php

namespace App\Models;

use App\Concerns\HasPublicFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjetSectionMedia extends Model
{
    use HasPublicFile;

    protected $table = 'projet_section_medias';

    protected $fillable = [
        'projet_id',
        'section_id',
        'type',
        'source_type',
        'url',
        'file_path',
        'nom_original',
        'taille',
        'user_id',
    ];

    /**
     * Retourne le projet auquel appartient ce média.
     */
    public function projet(): BelongsTo
    {
        return $this->belongsTo(ProjetRecherche::class, 'projet_id');
    }

    /**
     * Retourne la section à laquelle appartient ce média.
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(TypeProjetSection::class, 'section_id');
    }

    /**
     * Retourne l'auteur du média.
     */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Retourne l'URL publique pour les médias uploadés.
     * Pour les médias de type URL, retourner directement l'url.
     */
    public function getUrlPubliqueAttribute(): ?string
    {
        if ($this->source_type === 'upload' && $this->file_path) {
            return asset($this->file_path);
        }

        return $this->url;
    }
}
