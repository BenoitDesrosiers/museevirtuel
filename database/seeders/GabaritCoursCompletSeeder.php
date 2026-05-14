<?php

namespace Database\Seeders;

use App\Models\GabaritCours;
use App\Models\GabaritCoursObjectif;
use App\Models\GabaritEcheancierEtape;
use App\Models\GabaritTypeProjet;
use App\Models\GabaritTypeProjetSection;
use Illuminate\Database\Seeder;

/**
 * Peuple le gabarit "cours complet" avec les objectifs pédagogiques,
 * les types de projets et les étapes d'échéancier standards.
 *
 * Ce seeder est idempotent : relancer ne crée pas de doublons.
 * Source : consignes du projet "Votre histoire, notre histoire".
 */
class GabaritCoursCompletSeeder extends Seeder
{
    /**
     * Objectifs pédagogiques officiels du cours complet.
     * Source : plan de cours — projet "Votre histoire, notre histoire".
     *
     * @var list<string>
     */
    private const OBJECTIFS = [
        'Effectuer une étude de cas en lien avec l\'histoire du Québec par l\'entremise d\'une entrevue semi-dirigée.',
        'Appliquer la méthode historique sur une réalité humaine.',
        'Lier les différents concepts abordés dans le cours par l\'analyse d\'un récit de vie en lien avec un ou des événements ayant construit le Québec d\'aujourd\'hui.',
        'Construire son esprit critique et d\'ouverture vis-à-vis la perception d\'une situation historique.',
        'Développer ses qualités humaines en interagissant avec une personne aînée, témoin du passé.',
        'Développer des habiletés d\'écoute et de communication dans le cadre d\'une entrevue avec une personne aînée, témoin du passé.',
        'Contribuer au développement et à la pérennité des connaissances et du savoir de l\'héritage historique du patrimoine québécois.',
    ];

    /**
     * Types de projets avec leurs sections, dans l'ordre de réalisation.
     *
     * @var list<array{nom: string, description: string, ponderation: float, is_sommatif: bool, generer_page_titre: bool, generer_table_matieres: bool, sections: list<array{label: string, type: string}>}>
     */
    private const TYPES_PROJETS = [
        [
            'nom' => 'Plan de travail',
            'description' => 'Rédaction du plan de travail structuré selon les normes du cours complet.',
            'ponderation' => 10.00,
            'is_sommatif' => false,
            'generer_page_titre' => false,
            'generer_table_matieres' => false,
            'sections' => [
                ['label' => 'Sujet amené',                 'type' => 'texte'],
                ['label' => 'Sujet posé',                  'type' => 'texte'],
                ['label' => 'Développement chronologique', 'type' => 'paragraphes'],
                ['label' => 'Conclusion',                  'type' => 'texte'],
                ['label' => 'Références',                  'type' => 'texte'],
            ],
        ],
        [
            'nom' => "Schéma d'entrevue",
            'description' => "Préparation structurée de l'entrevue avec une personne âgée : concepts, dimensions, indicateurs et questions spécifiques.",
            'ponderation' => 30.00,
            'is_sommatif' => true,
            'generer_page_titre' => true,
            'generer_table_matieres' => false,
            'sections' => [
                ['label' => "Sujet de l'enquête", 'type' => 'texte'],
                ['label' => 'Concepts',            'type' => 'entrevue'],
            ],
        ],
        [
            'nom' => 'Projet de recherche',
            'description' => 'Projet de recherche documentaire sur un sujet d\'histoire du Québec.',
            'ponderation' => 60.00,
            'is_sommatif' => true,
            'generer_page_titre' => true,
            'generer_table_matieres' => true,
            'sections' => [
                ['label' => 'Introduction',  'type' => 'texte'],
                ['label' => 'Développement', 'type' => 'paragraphes'],
                ['label' => 'Conclusion',    'type' => 'individuel'],
            ],
        ],
    ];

    /**
     * Étapes d'échéancier par semaine.
     * Source : consignes du projet "Votre histoire, notre histoire" 2025.
     *
     * @var array<int, list<string>>
     */
    private const ETAPES_PAR_SEMAINE = [
        1 => [
            'Présentation des consignes du travail de session',
            'Formation des équipes',
            'Choix des thématiques',
        ],
        2 => [
            'Choix définitif de la thématique',
            'Début des recherches bibliographiques',
            'Répartition des tâches dans l\'équipe',
        ],
        3 => [
            'Remise du plan provisoire (formatif)',
        ],
        4 => [
            'Poursuite des recherches',
            'Rédaction des sections Introduction et Développement',
        ],
        5 => [
            'Remise de la fiche de recherche (15 %)',
            'Élaboration du schéma d\'entrevue',
            'Jumelage avec un témoin / participant',
            'Prise de rendez-vous pour l\'entrevue',
            'Construction de la grille d\'entrevue',
        ],
        6 => [
            'Validation du schéma d\'entrevue avec l\'enseignant',
        ],
        7 => [
            'Remise du schéma d\'entrevue (5 %)',
        ],
        8 => [
            'Correction des questions d\'entrevue selon les commentaires',
            'Confirmation de la date d\'entrevue',
            'Préparation du matériel d\'enregistrement',
        ],
        9 => [
            'Passation des entrevues (première vague)',
        ],
        10 => [
            'Passation des entrevues (deuxième vague)',
            'Saisie et transcription des entrevues',
        ],
        11 => [
            'Analyse des données recueillies',
            'Rédaction du plan détaillé du rapport',
        ],
        12 => [
            'Rédaction du rapport final',
            'Intégration des extraits d\'entrevues dans le développement',
        ],
        13 => [
            'Remise du rapport final (25 %)',
        ],
    ];

    /**
     * Peuple le gabarit cours complet.
     *
     * Idempotent : si le gabarit existe déjà, les sous-éléments ne sont
     * pas recréés tant que la table n'est pas vide.
     */
    public function run(): void
    {
        /** @var GabaritCours $gabarit */
        $gabarit = GabaritCours::firstOrCreate(
            ['slug' => 'cours_complet'],
            [
                'type_cours' => 'cours_complet',
                'nom' => 'Gabarit — Cours complet',
            ]
        );

        $this->seederObjectifs($gabarit);
        $this->seederTypesProjets($gabarit);
        $this->seederEcheancier($gabarit);
    }

    /**
     * Recrée les objectifs pédagogiques (supprime et réinsère pour rester à jour).
     */
    private function seederObjectifs(GabaritCours $gabarit): void
    {
        // Suppression + recréation pour que relancer le seeder mette les objectifs à jour
        $gabarit->objectifs()->delete();

        foreach (self::OBJECTIFS as $ordre => $contenu) {
            GabaritCoursObjectif::create([
                'gabarit_cours_id' => $gabarit->id,
                'contenu' => $contenu,
                'ordre' => $ordre + 1,
            ]);
        }
    }

    /**
     * Crée les types de projets avec leurs sections s'ils n'existent pas encore.
     */
    private function seederTypesProjets(GabaritCours $gabarit): void
    {
        if ($gabarit->typesProjets()->exists()) {
            return;
        }

        foreach (self::TYPES_PROJETS as $ordre => $data) {
            /** @var GabaritTypeProjet $typeProjet */
            $typeProjet = GabaritTypeProjet::create([
                'gabarit_cours_id' => $gabarit->id,
                'nom' => $data['nom'],
                'description' => $data['description'],
                'ponderation' => $data['ponderation'],
                'is_sommatif' => $data['is_sommatif'],
                'generer_page_titre' => $data['generer_page_titre'],
                'generer_table_matieres' => $data['generer_table_matieres'],
                'ordre' => $ordre + 1,
            ]);

            foreach ($data['sections'] as $sOrdre => $section) {
                GabaritTypeProjetSection::create([
                    'gabarit_type_projet_id' => $typeProjet->id,
                    'label' => $section['label'],
                    'type' => $section['type'],
                    'ordre' => $sOrdre + 1,
                ]);
            }
        }
    }

    /**
     * Crée les étapes d'échéancier s'elles n'existent pas encore.
     */
    private function seederEcheancier(GabaritCours $gabarit): void
    {
        if ($gabarit->echeancierEtapes()->exists()) {
            return;
        }

        $inserts = [];

        foreach (self::ETAPES_PAR_SEMAINE as $semaine => $etapes) {
            foreach ($etapes as $ordre => $etape) {
                $inserts[] = [
                    'gabarit_cours_id' => $gabarit->id,
                    'semaine' => $semaine,
                    'etape' => $etape,
                    'ordre' => $ordre,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        GabaritEcheancierEtape::insert($inserts);
    }
}
