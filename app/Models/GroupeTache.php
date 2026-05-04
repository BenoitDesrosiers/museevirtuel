<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupeTache extends Model
{
    protected $table = 'groupe_taches';

    protected $fillable = [
        'tache_id',
        'groupe_id',
        'assigne_a',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Retourne la tâche définie dans le TypeProjet.
     */
    public function tache(): BelongsTo
    {
        return $this->belongsTo(TypeProjetTache::class, 'tache_id');
    }

    /**
     * Retourne le groupe propriétaire de cette entrée de suivi.
     */
    public function groupe(): BelongsTo
    {
        return $this->belongsTo(Groupe::class, 'groupe_id');
    }

    /**
     * Retourne le membre du groupe assigné à cette tâche, s'il y en a un.
     */
    public function assigneA(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigne_a');
    }
}
