<?php

namespace App\Models;

use App\Concerns\HasPublicFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupeMedia extends Model
{
    use HasPublicFile;

    protected $table = 'groupe_medias';

    public const TRANSCRIPTION_EN_ATTENTE = 'en_attente';

    public const TRANSCRIPTION_EN_COURS = 'en_cours';

    public const TRANSCRIPTION_TERMINEE = 'terminé';

    public const TRANSCRIPTION_ERREUR = 'erreur';

    protected $fillable = [
        'groupe_id',
        'user_id',
        'nom_original',
        'file_path',
        'type',
        'taille',
        'transcription',
        'transcription_statut',
    ];

    protected $appends = ['url'];

    public function getUrlAttribute(): string
    {
        // Le paramètre ?v= force le navigateur à refetch l'image après une édition
        // (rotation, rognage…) qui modifie le fichier sans changer son chemin.
        return asset($this->file_path).'?v='.$this->updated_at->timestamp;
    }

    /**
     * Vérifie si une transcription est en cours ou en attente pour ce média.
     */
    public function isBeingTranscribed(): bool
    {
        return in_array($this->transcription_statut, [
            self::TRANSCRIPTION_EN_ATTENTE,
            self::TRANSCRIPTION_EN_COURS,
        ]);
    }

    /**
     * Retourne le groupe auquel appartient ce média.
     */
    public function groupe(): BelongsTo
    {
        return $this->belongsTo(Groupe::class);
    }

    /**
     * Retourne l'auteur du média.
     */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
