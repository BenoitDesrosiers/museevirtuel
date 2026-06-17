<?php

namespace Database\Seeders;

use App\Models\GabaritCours;
use App\Models\GabaritCoursObjectif;
use App\Models\GabaritCoursReference;
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
     * Revues historiques recommandées pour la recherche documentaire.
     * Source : consignes du projet "Votre histoire, notre histoire".
     *
     * @var list<array{nom: string, url: string}>
     */
    private const REFERENCES = [
        ['nom' => 'Cahier d\'histoire',                                                                    'url' => 'https://www.erudit.org/en/journals/histoire/'],
        ['nom' => 'Les Cahiers des Dix',                                                                   'url' => 'https://www.erudit.org/en/journals/cdd/'],
        ['nom' => 'Cap-aux-Diamants — La revue d\'histoire du Québec',                                     'url' => 'https://www.capauxdiamants.org/'],
        ['nom' => 'Courrier international',                                                                'url' => 'https://www.courrierinternational.com/'],
        ['nom' => 'Études d\'histoire religieuse',                                                         'url' => 'https://www.erudit.org/en/journals/ehr/'],
        ['nom' => 'Géo Histoire',                                                                          'url' => 'https://www.geo.fr/histoire'],
        ['nom' => 'Globe — Revue internationale d\'études québécoises',                                    'url' => 'https://www.erudit.org/en/journals/globe/'],
        ['nom' => 'Histoire Québec',                                                                       'url' => 'https://histoirequebec.qc.ca/'],
        ['nom' => 'Histoire sociale — Social History',                                                     'url' => 'https://hssh.journals.yorku.ca/'],
        ['nom' => 'L\'Histoire',                                                                           'url' => 'https://www.lhistoire.fr/'],
        ['nom' => 'Historia',                                                                              'url' => 'https://www.historia.fr/'],
        ['nom' => 'Journal of the Canadian Historical Association — Revue de la Société historique du Canada', 'url' => 'https://www.erudit.org/en/journals/jcha/'],
        ['nom' => 'Le Monde diplomatique',                                                                 'url' => 'https://www.monde-diplomatique.fr/'],
        ['nom' => 'National Geographic France',                                                            'url' => 'https://www.nationalgeographic.fr/'],
        ['nom' => 'Revue d\'histoire de l\'Amérique française',                                            'url' => 'https://www.erudit.org/en/journals/haf/'],
        ['nom' => 'Urban History Review — Revue d\'histoire urbaine',                                      'url' => 'https://www.erudit.org/en/journals/uhr/'],
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
        $this->seederCriteres($gabarit);
        $this->seederEcheancier($gabarit);
        $this->seederReferences($gabarit);
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
     * Crée les critères de correction du "Projet de recherche" s'ils n'existent pas encore.
     *
     * Source : Recherche documentaire_grille de correction.docx
     * Échelle commune : Excellente (4/4) → Bon (3/4) → Passable (2/4) → Mauvais (0/4).
     */
    private function seederCriteres(GabaritCours $gabarit): void
    {
        /** @var GabaritTypeProjet|null $typeProjet */
        $typeProjet = $gabarit->typesProjets()->where('nom', 'Projet de recherche')->first();

        if (! $typeProjet || $typeProjet->criteres()->exists()) {
            return;
        }

        // Indexer les sections par label pour les références rapides
        $sections = $typeProjet->sections->keyBy('label');

        $intro = $sections['Introduction'] ?? null;
        $dev = $sections['Développement'] ?? null;
        $conc = $sections['Conclusion'] ?? null;

        // ── Critères globaux (sans section) ──────────────────────────────────
        $typeProjet->criteres()->create([
            'gabarit_section_id' => null,
            'type' => 'positif',
            'contenu_type' => 'echelle',
            'pointage' => 10,
            'contenu' => 'Normes de présentation : page titre, table des matières, pagination.',
            'echelle' => [
                ['label' => 'Excellente', 'points' => 10,  'description' => 'La présentation est impeccable, incluant une page titre bien formatée, une table des matières complète et une pagination précise.'],
                ['label' => 'Bon',        'points' => 7.5, 'description' => 'La présentation est correcte avec une page titre et une table des matières, mais il peut y avoir quelques erreurs mineures dans la pagination.'],
                ['label' => 'Passable',   'points' => 5,   'description' => 'La présentation manque de clarté, avec une page titre présente mais une table des matières incomplète ou des erreurs de pagination.'],
                ['label' => 'Mauvais',    'points' => 0,   'description' => 'La présentation est inadéquate, sans page titre, table des matières ou pagination appropriée.'],
            ],
            'visible' => true,
            'ordre' => 1,
        ]);

        $typeProjet->criteres()->create([
            'gabarit_section_id' => null,
            'type' => 'positif',
            'contenu_type' => 'echelle',
            'pointage' => 6,
            'contenu' => 'Liste de références : six sources fiables et plus.',
            'echelle' => [
                ['label' => 'Excellente', 'points' => 6,   'description' => 'La recherche comprend plus de six sources fiables et pertinentes, démontrant une excellente compréhension du sujet.'],
                ['label' => 'Bon',        'points' => 4.5, 'description' => 'La recherche inclut six sources fiables, montrant une bonne compréhension du sujet.'],
                ['label' => 'Passable',   'points' => 3,   'description' => 'La recherche contient au moins quatre sources, mais certaines ne sont pas entièrement fiables.'],
                ['label' => 'Mauvais',    'points' => 0,   'description' => 'La recherche présente moins de quatre sources, dont plusieurs ne sont pas fiables.'],
            ],
            'visible' => true,
            'ordre' => 2,
        ]);

        $typeProjet->criteres()->create([
            'gabarit_section_id' => null,
            'type' => 'positif',
            'contenu_type' => 'echelle',
            'pointage' => 5,
            'contenu' => 'Liste de références : selon les normes de présentation.',
            'echelle' => [
                ['label' => 'Excellente', 'points' => 5,    'description' => 'Les références sont parfaitement formatées selon les normes de présentation, sans aucune erreur.'],
                ['label' => 'Bon',        'points' => 3.75, 'description' => 'Les références sont bien formatées, avec quelques erreurs mineures dans le style.'],
                ['label' => 'Passable',   'points' => 2.5,  'description' => 'Les références montrent des efforts de formatage, mais plusieurs erreurs sont présentes.'],
                ['label' => 'Mauvais',    'points' => 0,    'description' => 'Les références ne respectent pas les normes de présentation et sont mal formatées.'],
            ],
            'visible' => true,
            'ordre' => 3,
        ]);

        $typeProjet->criteres()->create([
            'gabarit_section_id' => null,
            'type' => 'positif',
            'contenu_type' => 'echelle',
            'pointage' => 5,
            'contenu' => 'Écriture cohérente et fluide.',
            'echelle' => [
                ['label' => 'Excellente', 'points' => 5,    'description' => 'Le texte est extrêmement bien structuré, avec des transitions fluides entre les idées.'],
                ['label' => 'Bon',        'points' => 3.75, 'description' => 'Le texte est généralement bien structuré, bien que certaines transitions puissent être améliorées.'],
                ['label' => 'Passable',   'points' => 2.5,  'description' => 'Le texte présente des incohérences dans la structure et les transitions entre les idées.'],
                ['label' => 'Mauvais',    'points' => 0,    'description' => 'Le texte est désorganisé et difficile à suivre, avec peu ou pas de transitions.'],
            ],
            'visible' => true,
            'ordre' => 4,
        ]);

        // ── Section Introduction ──────────────────────────────────────────────
        if ($intro) {
            $typeProjet->criteres()->create([
                'gabarit_section_id' => $intro->id,
                'type' => 'positif',
                'contenu_type' => 'echelle',
                'pointage' => 3,
                'contenu' => 'Introduction : sujet amené.',
                'echelle' => [
                    ['label' => 'Excellente', 'points' => 3,    'description' => "L'introduction présente de manière claire et engageante le sujet, suscitant un intérêt immédiat."],
                    ['label' => 'Bon',        'points' => 2.25, 'description' => "L'introduction présente le sujet de façon adéquate, mais manque d'éléments captivants."],
                    ['label' => 'Passable',   'points' => 1.5,  'description' => "L'introduction aborde le sujet, mais de manière vague ou peu structurée."],
                    ['label' => 'Mauvais',    'points' => 0,    'description' => "L'introduction ne présente pas le sujet ou est hors sujet."],
                ],
                'visible' => true,
                'ordre' => 1,
            ]);

            $typeProjet->criteres()->create([
                'gabarit_section_id' => $intro->id,
                'type' => 'positif',
                'contenu_type' => 'echelle',
                'pointage' => 3,
                'contenu' => 'Introduction : sujet posé.',
                'echelle' => [
                    ['label' => 'Excellente', 'points' => 3,    'description' => "L'introduction présente clairement le sujet et établit un contexte solide pour la recherche."],
                    ['label' => 'Bon',        'points' => 2.25, 'description' => "L'introduction présente le sujet, mais manque de détails contextuels."],
                    ['label' => 'Passable',   'points' => 1.5,  'description' => "L'introduction mentionne le sujet, mais reste vague et peu engageante."],
                    ['label' => 'Mauvais',    'points' => 0,    'description' => "L'introduction est confuse ou ne présente pas le sujet de manière adéquate."],
                ],
                'visible' => true,
                'ordre' => 2,
            ]);

            $typeProjet->criteres()->create([
                'gabarit_section_id' => $intro->id,
                'type' => 'positif',
                'contenu_type' => 'echelle',
                'pointage' => 3,
                'contenu' => 'Introduction : sujet divisé.',
                'echelle' => [
                    ['label' => 'Excellente', 'points' => 3,    'description' => "L'introduction présente clairement le sujet et le divise en sections pertinentes, démontrant une compréhension approfondie."],
                    ['label' => 'Bon',        'points' => 2.25, 'description' => "L'introduction aborde le sujet de manière adéquate et le divise en sections, mais certaines parties manquent de clarté."],
                    ['label' => 'Passable',   'points' => 1.5,  'description' => "L'introduction mentionne le sujet, mais la division en sections est incomplète ou peu claire."],
                    ['label' => 'Mauvais',    'points' => 0,    'description' => "L'introduction ne présente pas le sujet de manière claire et ne le divise pas en sections appropriées."],
                ],
                'visible' => true,
                'ordre' => 3,
            ]);
        }

        // ── Section Développement ─────────────────────────────────────────────
        if ($dev) {
            $typeProjet->criteres()->create([
                'gabarit_section_id' => $dev->id,
                'type' => 'positif',
                'contenu_type' => 'echelle',
                'pointage' => 10,
                'contenu' => 'Développement divisé en thématique ou chronologique.',
                'echelle' => [
                    ['label' => 'Excellente', 'points' => 10,  'description' => 'Le développement est clairement structuré, avec des thèmes ou des chronologies bien définis et pertinents.'],
                    ['label' => 'Bon',        'points' => 7.5, 'description' => 'Le développement est structuré, mais quelques thèmes ou chronologies manquent de clarté ou de pertinence.'],
                    ['label' => 'Passable',   'points' => 5,   'description' => 'Le développement présente une structure, mais les thèmes ou chronologies sont peu clairs ou mal définis.'],
                    ['label' => 'Mauvais',    'points' => 0,   'description' => 'Le développement manque de structure, avec des thèmes ou des chronologies absents ou inappropriés.'],
                ],
                'visible' => true,
                'ordre' => 1,
            ]);

            $typeProjet->criteres()->create([
                'gabarit_section_id' => $dev->id,
                'type' => 'positif',
                'contenu_type' => 'echelle',
                'pointage' => 15,
                'contenu' => 'Développement : contextualisation du sujet.',
                'echelle' => [
                    ['label' => 'Excellente', 'points' => 15,    'description' => 'Le sujet est parfaitement contextualisé avec des informations pertinentes et approfondies.'],
                    ['label' => 'Bon',        'points' => 11.25, 'description' => 'Le sujet est bien contextualisé, mais manque de certaines informations clés.'],
                    ['label' => 'Passable',   'points' => 7.5,   'description' => 'Le sujet est partiellement contextualisé, mais plusieurs éléments importants sont absents.'],
                    ['label' => 'Mauvais',    'points' => 0,     'description' => 'Le sujet est mal contextualisé, avec peu ou pas d\'informations pertinentes.'],
                ],
                'visible' => true,
                'ordre' => 2,
            ]);

            $typeProjet->criteres()->create([
                'gabarit_section_id' => $dev->id,
                'type' => 'positif',
                'contenu_type' => 'echelle',
                'pointage' => 20,
                'contenu' => 'Développement : présentation des faits historiques marquants ou pertinents.',
                'echelle' => [
                    ['label' => 'Excellente', 'points' => 20, 'description' => "Les faits historiques sont présentés de manière claire, précise et enrichie d'analyses approfondies."],
                    ['label' => 'Bon',        'points' => 15, 'description' => "Les faits historiques sont présentés de manière claire, mais manquent d'analyses approfondies."],
                    ['label' => 'Passable',   'points' => 10, 'description' => 'Les faits historiques sont présentés, mais avec des imprécisions et peu d\'analyses.'],
                    ['label' => 'Mauvais',    'points' => 0,  'description' => 'Les faits historiques sont mal présentés et manquent de pertinence et d\'analyse.'],
                ],
                'visible' => true,
                'ordre' => 3,
            ]);

            $typeProjet->criteres()->create([
                'gabarit_section_id' => $dev->id,
                'type' => 'positif',
                'contenu_type' => 'echelle',
                'pointage' => 10,
                'contenu' => 'Développement : chaque nouvelle information est soutenue par une source fiable.',
                'echelle' => [
                    ['label' => 'Excellente', 'points' => 10,  'description' => 'Les informations présentées sont toutes soutenues par des sources fiables et pertinentes, démontrant une recherche approfondie.'],
                    ['label' => 'Bon',        'points' => 7.5, 'description' => 'La plupart des informations sont soutenues par des sources fiables, mais quelques éléments manquent de références claires.'],
                    ['label' => 'Passable',   'points' => 5,   'description' => 'Certaines informations sont soutenues par des sources, mais plusieurs manquent de fiabilité ou de pertinence.'],
                    ['label' => 'Mauvais',    'points' => 0,   'description' => 'Peu ou pas d\'informations sont soutenues par des sources fiables, ce qui nuit à la crédibilité du travail.'],
                ],
                'visible' => true,
                'ordre' => 4,
            ]);
        }

        // ── Section Conclusion ────────────────────────────────────────────────
        if ($conc) {
            $typeProjet->criteres()->create([
                'gabarit_section_id' => $conc->id,
                'type' => 'positif',
                'contenu_type' => 'echelle',
                'pointage' => 5,
                'contenu' => "Conclusion : ouverture vers le développement d'autres connaissances.",
                'echelle' => [
                    ['label' => 'Excellente', 'points' => 5,    'description' => 'La conclusion démontre une compréhension approfondie et propose des pistes claires pour approfondir les connaissances.'],
                    ['label' => 'Bon',        'points' => 3.75, 'description' => 'La conclusion présente des idées pertinentes pour le développement futur des connaissances.'],
                    ['label' => 'Passable',   'points' => 2.5,  'description' => 'La conclusion mentionne quelques aspects pour élargir les connaissances, mais manque de profondeur.'],
                    ['label' => 'Mauvais',    'points' => 0,    'description' => 'La conclusion ne propose pas d\'ouverture vers le développement de nouvelles connaissances.'],
                ],
                'visible' => true,
                'ordre' => 1,
            ]);

            $typeProjet->criteres()->create([
                'gabarit_section_id' => $conc->id,
                'type' => 'positif',
                'contenu_type' => 'echelle',
                'pointage' => 5,
                'contenu' => "Conclusion : introduire l'objectif de recherche et présenter l'entrevue et ses avantages.",
                'echelle' => [
                    ['label' => 'Excellente', 'points' => 5,    'description' => "L'objectif de recherche est clairement défini et l'entrevue est présentée de manière exhaustive, en soulignant tous ses avantages."],
                    ['label' => 'Bon',        'points' => 3.75, 'description' => "L'objectif de recherche est bien défini et l'entrevue est présentée, mais certains avantages pourraient être mieux expliqués."],
                    ['label' => 'Passable',   'points' => 2.5,  'description' => "L'objectif de recherche est mentionné, mais l'entrevue et ses avantages sont abordés de manière superficielle."],
                    ['label' => 'Mauvais',    'points' => 0,    'description' => "L'objectif de recherche est flou et l'entrevue ainsi que ses avantages ne sont pas présentés."],
                ],
                'visible' => true,
                'ordre' => 2,
            ]);
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

    /**
     * Recrée les références bibliographiques (supprime et réinsère pour rester à jour).
     */
    private function seederReferences(GabaritCours $gabarit): void
    {
        // Suppression + recréation pour que relancer le seeder mette les références à jour
        $gabarit->references()->delete();

        foreach (self::REFERENCES as $ordre => $data) {
            GabaritCoursReference::create([
                'gabarit_cours_id' => $gabarit->id,
                'nom' => $data['nom'],
                'url' => $data['url'],
                'ordre' => $ordre + 1,
            ]);
        }
    }
}
