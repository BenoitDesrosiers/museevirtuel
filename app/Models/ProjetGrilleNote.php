<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjetGrilleNote extends Model
{
    protected $table = 'projet_grille_notes';

    protected $fillable = [
        'projet_id',
        'user_id',
        'critere_id',
        'note',
    ];

    protected function casts(): array
    {
        return ['note' => 'integer'];
    }

    /**
     * Calcule la note finale pondérée d'un étudiant sur 100 pour un projet avec grille personnalisée.
     *
     * Formule : Σ(note/4 × pondération) − Σ(déductions des malus appliqués), planché à 0.
     * Retourne null si aucune note n'a encore été saisie pour cet étudiant.
     *
     * @param  ProjetRecherche  $projet  Doit avoir les relations notesGrille et malusAppliques chargées.
     */
    public static function noteFinale(ProjetRecherche $projet, User $etudiant): ?float
    {
        // Réutiliser la collection déjà chargée si disponible — évite le N+1
        $notes = $projet->relationLoaded('notesGrille')
            ? $projet->notesGrille
                ->where('user_id', $etudiant->id)
                ->keyBy('critere_id')
            : $projet->notesGrille()
                ->with('critere')
                ->where('user_id', $etudiant->id)
                ->get()
                ->keyBy('critere_id');

        if ($notes->isEmpty()) {
            return null;
        }

        $base = 0.0;

        foreach ($notes as $note) {
            // La pondération est portée par le critère — on eager-load ou on accède via relation
            $ponderation = $note->relationLoaded('critere')
                ? $note->critere->ponderation
                : GrilleCritere::find($note->critere_id)?->ponderation ?? 0;

            $base += ($note->note / 4) * $ponderation;
        }

        // Déduire les malus appliqués à cet étudiant
        $malusAppliques = $projet->relationLoaded('malusAppliques')
            ? $projet->malusAppliques
                ->where('user_id', $etudiant->id)
                ->where('applique', true)
            : $projet->malusAppliques()
                ->with('malus')
                ->where('user_id', $etudiant->id)
                ->where('applique', true)
                ->get();

        $deductions = $malusAppliques->sum(fn ($m) => $m->relationLoaded('malus')
            ? (float) $m->malus->deduction
            : (float) (GrilleMalus::find($m->malus_id)?->deduction ?? 0)
        );

        // Déduire les malus des annotations de correction inline (enseignant).
        // cible_user_id = null → s'applique à tous les étudiants du groupe.
        $annotationsMalus = $projet->relationLoaded('annotations')
            ? $projet->annotations
                ->where('type', 'correction')
                ->filter(fn ($a) => $a->cible_user_id === null || $a->cible_user_id === $etudiant->id)
                ->whereNotNull('points_malus')
                ->sum('points_malus')
            : $projet->annotations()
                ->where('type', 'correction')
                ->where(fn ($q) => $q->whereNull('cible_user_id')->orWhere('cible_user_id', $etudiant->id))
                ->whereNotNull('points_malus')
                ->sum('points_malus');

        $deductions += (float) $annotationsMalus;

        return round(max(0.0, $base - $deductions), 2);
    }

    /**
     * Retourne le projet de recherche auquel appartient cette note.
     */
    public function projet(): BelongsTo
    {
        return $this->belongsTo(ProjetRecherche::class, 'projet_id');
    }

    /**
     * Retourne l'étudiant concerné par cette note.
     */
    public function etudiant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Retourne le critère de grille auquel se rapporte cette note.
     */
    public function critere(): BelongsTo
    {
        return $this->belongsTo(GrilleCritere::class, 'critere_id');
    }
}
