<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjetNote extends Model
{
    /**
     * Grille de correction — critères avec leur libellé et leur poids (sur 100).
     * Scores possibles : 0 (mauvais), 2 (passable), 3 (bon), 4 (excellent).
     * Note finale = Σ(note / 4 × poids).
     *
     * @var array<string, array{label: string, poids: int}>
     */
    public const CRITERES = [
        'normes_presentation' => ['label' => 'Normes de présentation (page titre, table des matières, pagination)', 'poids' => 10],
        'introduction_amener' => ['label' => 'Introduction — sujet amené', 'poids' => 3],
        'introduction_poser' => ['label' => 'Introduction — sujet posé', 'poids' => 3],
        'introduction_diviser' => ['label' => 'Introduction — sujet divisé', 'poids' => 3],
        'developpement_structure' => ['label' => 'Développement — structure thématique ou chronologique', 'poids' => 10],
        'developpement_contextualisation' => ['label' => 'Développement — contextualisation du sujet', 'poids' => 15],
        'developpement_faits' => ['label' => 'Développement — présentation des faits historiques marquants', 'poids' => 20],
        'developpement_sources' => ['label' => 'Développement — informations soutenues par des sources fiables', 'poids' => 10],
        'conclusion_ouverture' => ['label' => 'Conclusion — ouverture vers d\'autres connaissances', 'poids' => 5],
        'conclusion_objectif' => ['label' => 'Conclusion — objectif de recherche et présentation de l\'entrevue', 'poids' => 5],
        'references_quantite' => ['label' => 'Liste de références — six sources fiables et plus', 'poids' => 6],
        'references_normes' => ['label' => 'Liste de références — selon les normes de présentation', 'poids' => 5],
        'ecriture' => ['label' => 'Écriture cohérente et fluide', 'poids' => 5],
    ];

    /**
     * Regroupe les critères par section de l'interface pour l'affichage inline.
     * Chaque clé de section correspond à un bloc Note dans Show.vue.
     *
     * @var array<string, array<int, string>>
     */
    public const CRITERES_PAR_SECTION = [
        'page_titre' => ['normes_presentation'],
        'introduction_amener' => ['introduction_amener'],
        'introduction_poser' => ['introduction_poser'],
        'introduction_diviser' => ['introduction_diviser'],
        'developpement' => ['developpement_structure', 'developpement_contextualisation', 'developpement_faits', 'developpement_sources'],
        'conclusion' => ['conclusion_ouverture', 'conclusion_objectif'],
        'references_et_ecriture' => ['references_quantite', 'references_normes', 'ecriture'],
    ];

    protected $fillable = [
        'projet_id',
        'user_id',
        'critere',
        'note',
    ];

    /**
     * Calcule la note finale pondérée d'un étudiant sur 100 pour un projet donné.
     * Les critères non remplis valent 4 (excellent) par défaut — un étudiant non noté
     * part donc de 100 %, et la note descend à mesure que l'enseignant évalue.
     *
     * @return float Toujours entre 0 et 100. Vaut 100.0 si aucune note n'a été saisie.
     */
    public static function noteFinale(ProjetRecherche $projet, User $etudiant): float
    {
        // Réutiliser la collection déjà chargée si disponible — évite N+1 sur show() et upsertNote()
        $notes = $projet->relationLoaded('notes')
            ? $projet->notes->where('user_id', $etudiant->id)->keyBy('critere')
            : $projet->notes()->where('user_id', $etudiant->id)->get()->keyBy('critere');

        $total = 0.0;

        foreach (self::CRITERES as $cle => $config) {
            // Critère non rempli → note maximale (4 = excellent) par défaut
            $note = $notes->has($cle) ? $notes[$cle]->note : 4;
            $total += ($note / 4) * $config['poids'];
        }

        return round($total, 2);
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
}
