<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjetRecherche extends Model
{
    protected $table = 'projets_recherche';

    protected $fillable = [
        'groupe_id',
        'type_projet_id',
        'titre_projet',
        'page_titre_contenu',
        'table_matieres_contenu',
        'correction_visible',
        'verrouille',
        'date_remise',
        'remis_le',
        'remises_multiples',
        'retard_permis',
    ];

    /**
     * Retourne les casts de colonnes pour l'hydratation automatique.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'correction_visible' => 'boolean',
            'verrouille' => 'boolean',
            'remises_multiples' => 'boolean',
            'retard_permis' => 'boolean',
            'date_remise' => 'datetime',
            'remis_le' => 'datetime',
        ];
    }

    /**
     * Indique si le travail peut encore être remis par l'équipe.
     *
     * Les paramètres (date_remise, remises_multiples, retard_permis) sont lus
     * depuis le TypeProjet associé s'il est chargé, sinon depuis les colonnes
     * locales (fallback rétrocompatible).
     *
     * Retourne false si :
     * - déjà remis et remises multiples non autorisées, OU
     * - la date limite est dépassée et les remises en retard ne sont pas permises.
     */
    public function peutEtreRemis(): bool
    {
        $tp = $this->relationLoaded('typeProjet') ? $this->typeProjet : null;

        $dateRemise = $tp?->date_remise ?? $this->date_remise;
        $retardPermis = $tp !== null ? $tp->retard_permis : $this->retard_permis;
        $remisesMultiples = $tp !== null ? $tp->remises_multiples : $this->remises_multiples;

        // Blocage si délai dépassé et retard non permis
        if (! $retardPermis && $dateRemise !== null && now()->gt($dateRemise)) {
            return false;
        }

        if ($this->remis_le === null) {
            return true;
        }

        return (bool) $remisesMultiples;
    }

    /**
     * Retourne le groupe auquel appartient ce projet.
     */
    public function groupe(): BelongsTo
    {
        return $this->belongsTo(Groupe::class);
    }

    /**
     * Retourne le type de projet associé.
     */
    public function typeProjet(): BelongsTo
    {
        return $this->belongsTo(TypeProjet::class, 'type_projet_id');
    }

    /**
     * Retourne la grille de correction via le type de projet.
     */
    public function grille(): ?GrilleCorrection
    {
        return $this->typeProjet?->grille;
    }

    /**
     * Retourne les notes de la grille personnalisée (une ligne par étudiant × critère).
     */
    public function notesGrille(): HasMany
    {
        return $this->hasMany(ProjetGrilleNote::class, 'projet_id');
    }

    /**
     * Retourne les malus appliqués par l'enseignant pour ce projet (une ligne par étudiant × malus).
     */
    public function malusAppliques(): HasMany
    {
        return $this->hasMany(ProjetGrilleMalus::class, 'projet_id');
    }

    /**
     * Retourne les conclusions individuelles des membres de l'équipe.
     */
    public function conclusions(): HasMany
    {
        return $this->hasMany(ProjetConclusion::class, 'projet_id');
    }

    /**
     * Retourne les commentaires de l'enseignant par champ.
     */
    public function commentaires(): HasMany
    {
        return $this->hasMany(ProjetCommentaire::class, 'projet_id');
    }

    /**
     * Retourne les notes de la grille de correction.
     */
    public function notes(): HasMany
    {
        return $this->hasMany(ProjetNote::class, 'projet_id');
    }

    /**
     * Retourne les annotations inline de l'enseignant sur les sections du projet.
     */
    public function annotations(): HasMany
    {
        return $this->hasMany(ProjetAnnotation::class, 'projet_id');
    }

    /**
     * Retourne les votes de remise des membres de l'équipe.
     */
    public function votes(): HasMany
    {
        return $this->hasMany(ProjetVoteRemise::class, 'projet_id');
    }

    /**
     * Retourne les contenus des sections dynamiques de ce projet.
     */
    public function sectionContenus(): HasMany
    {
        return $this->hasMany(ProjetSectionContenu::class, 'projet_id');
    }

    /**
     * Retourne les paragraphes des sections de type 'paragraphes' triés par ordre.
     */
    public function sectionParagraphes(): HasMany
    {
        return $this->hasMany(ProjetSectionParagraphe::class, 'projet_id')->orderBy('ordre');
    }

    /**
     * Retourne les paragraphes de développement triés par ordre.
     */
    public function developpements(): HasMany
    {
        return $this->hasMany(ProjetDeveloppement::class, 'projet_id')->orderBy('ordre');
    }

    /**
     * Retourne les concepts d'entrevue du projet, triés par ordre.
     */
    public function entrevueConcepts(): HasMany
    {
        return $this->hasMany(EntrevueConcept::class, 'projet_id')->orderBy('ordre');
    }

    /**
     * Retourne les notes de renvoi (endnotes) du projet, triées par numéro.
     */
    public function renvois(): HasMany
    {
        return $this->hasMany(ProjetRenvoi::class, 'projet_id')->orderBy('numero');
    }

    /**
     * Retourne les médias de section (vidéo/audio) du projet.
     */
    public function sectionMedias(): HasMany
    {
        return $this->hasMany(ProjetSectionMedia::class, 'projet_id');
    }

    /**
     * Retourne les questions choisies par l'équipe dans la banque de questions.
     */
    public function questionsChoisies(): HasMany
    {
        return $this->hasMany(ProjetQuestionChoisie::class, 'projet_id');
    }

    /**
     * Retourne les schémas visuels du projet (sections de type schema_visuel).
     */
    public function schemaVisuels(): HasMany
    {
        return $this->hasMany(ProjetSchemaVisuel::class, 'projet_id');
    }

    /**
     * Calcule le pourcentage de complétion du contenu partagé (hors conclusions).
     *
     * Basé uniquement sur les sections de type 'texte' du TypeProjet.
     * Le titre_projet est toujours inclus dans le calcul.
     * Les sections de type 'paragraphes' et 'individuel' sont exclues du calcul
     * car leur saisie ne passe pas par ProjetSectionContenu.
     *
     * @return int Pourcentage entre 0 et 100.
     */
    public function completion(): int
    {
        $typeProjet = $this->relationLoaded('typeProjet') ? $this->typeProjet : $this->typeProjet()->with('sections')->first();
        $sections = $typeProjet?->sections ?? collect();

        // Seules les sections de type 'texte' sont mesurables via sectionContenus
        $sectionsTexte = $sections->filter(fn ($s) => ($s->type ?? 'texte') === 'texte');

        $total = 1 + $sectionsTexte->count();
        $remplisTotal = (trim(strip_tags((string) ($this->titre_projet ?? ''))) !== '') ? 1 : 0;

        $contenusParSection = $this->relationLoaded('sectionContenus')
            ? $this->sectionContenus->keyBy('section_id')
            : $this->sectionContenus()->get()->keyBy('section_id');

        foreach ($sectionsTexte as $section) {
            $contenu = $contenusParSection->get($section->id)?->contenu ?? '';
            if (trim(strip_tags((string) $contenu)) !== '') {
                $remplisTotal++;
            }
        }

        if ($total === 0) {
            return 0;
        }

        return (int) round($remplisTotal / $total * 100);
    }
}
