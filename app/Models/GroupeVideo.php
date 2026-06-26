<?php

namespace App\Models;

use App\Concerns\HasPublicFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupeVideo extends Model
{
    use HasPublicFile;

    // Valeurs de traitement_statut — centralisées ici pour éviter des chaînes dispersées
    // dans les Jobs, le Controller et le modèle lui-même.
    public const TRAITEMENT_EN_ATTENTE = 'en_attente';

    public const TRAITEMENT_EN_COURS = 'en_cours';

    public const TRAITEMENT_TERMINE = 'terminé';

    public const TRAITEMENT_ERREUR = 'erreur';

    // Valeurs de transcription_statut — même cycle de vie que le traitement FFmpeg.
    public const TRANSCRIPTION_EN_ATTENTE = 'en_attente';

    public const TRANSCRIPTION_EN_COURS = 'en_cours';

    public const TRANSCRIPTION_TERMINEE = 'terminé';

    public const TRANSCRIPTION_ERREUR = 'erreur';

    protected $table = 'groupe_videos';

    protected $fillable = [
        'groupe_id',
        'user_id',
        'titre',
        'description',
        'nom_original',
        'file_path',
        'taille',
        'duree',
        'thumbnail_path',
        'statut',
        'traitement_statut',
        'transcription',
        'transcription_segments',
        'transcription_statut',
    ];

    protected $appends = ['url', 'thumbnail_url'];

    /**
     * Définit les conversions de type pour les attributs du modèle.
     */
    protected function casts(): array
    {
        return [
            'taille' => 'integer',
            'duree' => 'integer',
            'transcription_segments' => 'array',
        ];
    }

    /**
     * Retourne l'URL de streaming sécurisée de la vidéo.
     *
     * La vidéo est stockée hors webroot (storage/app/private/) et servie
     * via un endpoint authentifié qui vérifie les droits via la policy.
     */
    public function getUrlAttribute(): string
    {
        return route('groupes.videos.stream', $this->id);
    }

    /**
     * Retourne le chemin absolu du fichier vidéo dans le stockage privé.
     *
     * Le fichier est hors webroot : storage/app/private/{file_path}.
     */
    public function absolutePath(): string
    {
        return storage_path('app/private/'.$this->file_path);
    }

    /**
     * Supprime le fichier vidéo physique (stockage privé) puis l'enregistrement.
     *
     * Surcharge HasPublicFile::deleteWithFile() car les vidéos sont dans
     * storage/app/private/ et non dans public/.
     */
    public function deleteWithFile(): ?bool
    {
        $fullPath = $this->absolutePath();

        if (file_exists($fullPath)) {
            unlink($fullPath);
        }

        return $this->delete();
    }

    /**
     * Retourne l'URL publique de la miniature, ou null si absente.
     */
    public function getThumbnailUrlAttribute(): ?string
    {
        return $this->thumbnail_path ? asset($this->thumbnail_path) : null;
    }

    /**
     * Supprime la miniature, le fichier vidéo principal et l'enregistrement.
     *
     * Utilisé lors de la suppression de la vidéo pour garantir qu'aucun
     * fichier orphelin ne reste sur disque.
     */
    public function deleteWithFileAndThumbnail(): ?bool
    {
        if ($this->thumbnail_path) {
            $thumbFull = public_path($this->thumbnail_path);
            if (file_exists($thumbFull)) {
                unlink($thumbFull);
            }
        }

        return $this->deleteWithFile();
    }

    /**
     * Indique si le traitement FFmpeg est encore en cours.
     */
    public function isBeingProcessed(): bool
    {
        return in_array($this->traitement_statut, [self::TRAITEMENT_EN_ATTENTE, self::TRAITEMENT_EN_COURS]);
    }

    /**
     * Indique si une transcription Whisper est déjà en cours ou en attente.
     */
    public function isBeingTranscribed(): bool
    {
        return in_array($this->transcription_statut, [self::TRANSCRIPTION_EN_ATTENTE, self::TRANSCRIPTION_EN_COURS]);
    }

    /**
     * Retourne le groupe auquel appartient cette vidéo.
     */
    public function groupe(): BelongsTo
    {
        return $this->belongsTo(Groupe::class);
    }

    /**
     * Retourne l'auteur de la vidéo.
     */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
