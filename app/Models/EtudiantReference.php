<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EtudiantReference extends Model
{
    protected $fillable = [
        'user_id',
        'zotero_item_key',
        'titre',
        'auteurs',
        'annee',
        'type_source',
        'url',
        'doi',
        'publication',
        'ordre',
    ];

    /**
     * Retourne les casts de colonnes pour l'hydratation automatique.
     *
     * Les auteurs sont sérialisés en JSON (tableau de {prenom, nom}).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'auteurs' => 'array',
            'annee' => 'integer',
            'ordre' => 'integer',
        ];
    }

    /**
     * Retourne l'étudiant propriétaire de cette référence.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Indique si cette référence a été importée depuis Zotero.
     */
    public function estDepuisZotero(): bool
    {
        return $this->zotero_item_key !== null;
    }

    /**
     * Formate les auteurs en une chaîne lisible (ex. « Curie, M. ; Pasteur, L. »).
     */
    public function auteursFormates(): string
    {
        if (empty($this->auteurs)) {
            return '';
        }

        return collect($this->auteurs)
            ->map(function (array $auteur): string {
                $nom = $auteur['nom'] ?? '';
                $prenom = $auteur['prenom'] ?? '';
                $initiale = $prenom ? mb_substr($prenom, 0, 1).'. ' : '';

                return trim("{$nom}, {$initiale}");
            })
            ->implode('; ');
    }
}
