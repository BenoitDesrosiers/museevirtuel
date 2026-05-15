<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EtudiantZoteroCredential extends Model
{
    protected $fillable = [
        'user_id',
        'zotero_user_id',
        'api_key',
        'synchronise_le',
    ];

    /**
     * Retourne les casts de colonnes pour l'hydratation automatique.
     *
     * La clé API est chiffrée au repos — elle n'est jamais stockée en clair.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'synchronise_le' => 'datetime',
        ];
    }

    /**
     * Retourne l'étudiant propriétaire de ces credentials Zotero.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
