<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupeVideoChapitre extends Model
{
    protected $table = 'groupe_video_chapitres';

    protected $fillable = [
        'video_id',
        'label',
        'debut',
        'fin',
        'ordre',
    ];

    /**
     * Définit les conversions de type pour les attributs du modèle.
     */
    protected function casts(): array
    {
        return [
            'debut' => 'float',
            'fin' => 'float',
            'ordre' => 'integer',
        ];
    }

    /**
     * Retourne la vidéo à laquelle appartient ce chapitre.
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(GroupeVideo::class, 'video_id');
    }
}
