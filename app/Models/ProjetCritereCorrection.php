<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjetCritereCorrection extends Model
{
    protected $table = 'projet_critere_corrections';

    protected $fillable = [
        'projet_id',
        'critere_id',
        'user_id',
        'points',
        'commentaire',
        'verifie',
        'source_id',
    ];

    /**
     * Retourne les casts de colonnes pour l'hydratation automatique.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'points' => 'decimal:2',
            'verifie' => 'boolean',
        ];
    }

    /**
     * Indique si cette correction s'applique à tous les membres du groupe
     * (par opposition à un étudiant individuel).
     */
    public function estPourGroupe(): bool
    {
        return $this->user_id === null;
    }

    /**
     * Retourne le nombre de points effectivement accordés ou déduits.
     *
     * Pour un critère positif avec `verifie = true` et `points = null`,
     * on retourne le pointage complet du critère associé.
     */
    public function pointsEffectifs(): float
    {
        if ($this->verifie && $this->points === null && $this->relationLoaded('critere')) {
            return (float) $this->critere->pointage;
        }

        return (float) ($this->points ?? 0);
    }

    /**
     * Retourne le projet de recherche auquel appartient cette correction.
     */
    public function projet(): BelongsTo
    {
        return $this->belongsTo(ProjetRecherche::class, 'projet_id');
    }

    /**
     * Retourne le critère évalué par cette correction.
     */
    public function critere(): BelongsTo
    {
        return $this->belongsTo(TypeProjetCritere::class, 'critere_id');
    }

    /**
     * Retourne l'étudiant ciblé par cette correction, ou null si elle vise tout le groupe.
     */
    public function etudiant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Retourne la correction d'origine dont ce record est un clone, le cas échéant.
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_id');
    }

    /**
     * Retourne les corrections clonées à partir de cette correction.
     *
     * Utilisé quand l'enseignant duplique une correction « groupe » pour
     * appliquer des points différents à des étudiants individuels.
     */
    public function clones(): HasMany
    {
        return $this->hasMany(self::class, 'source_id');
    }
}
