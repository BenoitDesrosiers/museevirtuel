<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsentementVideo extends Model
{
    protected $table = 'consentement_videos';

    protected $fillable = [
        'user_id',
        'projet_id',
        'type',
        'accepte',
        'signature',
        'signed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accepte' => 'boolean',
            'signed_at' => 'datetime',
        ];
    }

    /**
     * Retourne l'utilisateur qui a signé le consentement.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Retourne le projet associé au consentement (nullable).
     */
    public function projet(): BelongsTo
    {
        return $this->belongsTo(ProjetRecherche::class, 'projet_id');
    }
}
