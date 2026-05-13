<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class VisioConference extends Model
{
    protected $table = 'visio_conferences';

    protected $fillable = [
        'cours_id',
        'groupe_id',
        'animateur_id',
        'jitsi_room',
        'titre',
        'scheduled_at',
        'started_at',
        'ended_at',
        'recording_url',
        'recording_path',
    ];

    /**
     * Supprime le fichier d'enregistrement associé avant la suppression du modèle.
     * Évite les fichiers orphelins dans le storage.
     */
    protected static function booted(): void
    {
        static::deleting(function (VisioConference $visio): void {
            if ($visio->recording_path) {
                Storage::delete($visio->recording_path);
            }
        });
    }

    /**
     * Retourne les casts de colonnes pour l'hydratation automatique.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /**
     * Retourne le cours auquel appartient cette visioconférence.
     */
    public function cours(): BelongsTo
    {
        return $this->belongsTo(Cours::class);
    }

    /**
     * Retourne le groupe concerné (null si session pour tout le cours).
     */
    public function groupe(): BelongsTo
    {
        return $this->belongsTo(Groupe::class);
    }

    /**
     * Retourne l'enseignant animateur de la session.
     */
    public function animateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'animateur_id');
    }

    /**
     * Indique si la session est actuellement en cours.
     */
    public function isActive(): bool
    {
        return $this->started_at !== null && $this->ended_at === null;
    }

    /**
     * Indique si la session est terminée.
     */
    public function isEnded(): bool
    {
        return $this->ended_at !== null;
    }
}
