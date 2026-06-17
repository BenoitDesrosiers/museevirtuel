<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjetCritereEtudiantCoche extends Model
{
    protected $table = 'projet_critere_coches';

    protected $fillable = [
        'projet_id',
        'critere_id',
        'user_id',
    ];

    /**
     * Retourne le projet de recherche auquel appartient cette coche.
     */
    public function projet(): BelongsTo
    {
        return $this->belongsTo(ProjetRecherche::class, 'projet_id');
    }

    /**
     * Retourne le critère coché.
     */
    public function critere(): BelongsTo
    {
        return $this->belongsTo(TypeProjetCritere::class, 'critere_id');
    }

    /**
     * Retourne l'étudiant qui a coché ce critère comme indicateur personnel.
     */
    public function etudiant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
