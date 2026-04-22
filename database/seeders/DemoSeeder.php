<?php

namespace Database\Seeders;

use App\Models\Classe;
use App\Models\Cours;
use App\Models\EntrevueConcept;
use App\Models\EntrevueLigne;
use App\Models\GrilleCorrection;
use App\Models\GrilleCritere;
use App\Models\GrilleMalus;
use App\Models\Groupe;
use App\Models\ProjetConclusion;
use App\Models\ProjetRecherche;
use App\Models\ProjetSectionContenu;
use App\Models\ProjetSectionParagraphe;
use App\Models\Thematique;
use App\Models\TypeProjet;
use App\Models\TypeProjetSection;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    private const CRITERES = [
        ['label' => 'Introduction : mise en contexte, problématique et annonce du plan',  'ponderation' => 15],
        ['label' => 'Qualité et diversité des sources bibliographiques (min. 5 sources)',  'ponderation' => 15],
        ['label' => 'Développement : profondeur de l\'analyse et pertinence des arguments', 'ponderation' => 30],
        ['label' => 'Intégration et exploitation des données d\'entrevue',                  'ponderation' => 20],
        ['label' => 'Conclusion : synthèse et ouverture',                                  'ponderation' => 10],
        ['label' => 'Présentation, structure et respect des normes de mise en page',        'ponderation' => 10],
    ];

    private const MALUS = [
        [
            'label' => 'Fautes de français',
            'deduction' => 0.5,
            'description' => '0,5 point déduit par faute (orthographe, grammaire, syntaxe), jusqu\'à un maximum de 5 points.',
        ],
        [
            'label' => 'Remise en retard',
            'deduction' => 5.0,
            'description' => '5 points déduits par jour de retard.',
        ],
        [
            'label' => 'Non-respect des normes de présentation',
            'deduction' => 2.0,
            'description' => 'Police, marges, espacement ou numérotation non conformes au guide de présentation.',
        ],
        [
            'label' => 'Absence d\'entrevue',
            'deduction' => 10.0,
            'description' => 'L\'équipe n\'a pas réalisé d\'entrevue avec un témoin ou participant.',
        ],
    ];

    public function run(): void
    {
        // ─── Enseignant ───────────────────────────────────────────────────────
        /** @var User $prof */
        $prof = User::updateOrCreate(
            ['email' => 'prof@demo.com'],
            [
                'prenom' => 'Sophie',
                'nom' => 'Marchand',
                'password' => Hash::make('password'),
                'role' => 'enseignant',
                'email_verified_at' => now(),
            ]
        );

        // ─── Thématique ───────────────────────────────────────────────────────
        // On cherche par enseignant_id pour que EtablissementSeeder puisse
        // backfiller etablissement_id via $prof->thematiques()->update().
        /** @var Thematique $thematique */
        $thematique = Thematique::firstOrCreate(
            ['nom' => 'La Révolution tranquille', 'enseignant_id' => $prof->id],
            [
                'description' => 'Période de modernisation profonde du Québec : laïcisation, nationalisme, essor de l\'État québécois et transformations culturelles majeures.',
                'periode_historique' => '1960 – 1980',
                'enseignant_id' => $prof->id,
            ]
        );

        // ─── Cours ────────────────────────────────────────────────────────────
        /** @var Cours $cours */
        $cours = Cours::firstOrCreate(
            ['code' => '330-DEM-01', 'enseignant_id' => $prof->id],
            [
                'nom_cours' => 'Histoire du Québec — Démo',
                'description' => 'Cours de démonstration pour présentation.',
                'code' => '330-DEM-01',
                'groupe' => '00001',
                'enseignant_id' => $prof->id,
            ]
        );

        // ─── Étudiants ────────────────────────────────────────────────────────
        $etudiantsData = [
            ['prenom' => 'Léa',     'nom' => 'Tremblay', 'no_da' => 'DA100001', 'email' => 'etudiant1@demo.com'],
            ['prenom' => 'Maxime',  'nom' => 'Gagnon',   'no_da' => 'DA100002', 'email' => 'etudiant2@demo.com'],
            ['prenom' => 'Camille', 'nom' => 'Roy',       'no_da' => 'DA100003', 'email' => 'etudiant3@demo.com'],
            ['prenom' => 'Nathan',  'nom' => 'Bouchard',  'no_da' => 'DA100004', 'email' => 'etudiant4@demo.com'],
        ];

        /** @var User[] $etudiants */
        $etudiants = [];
        foreach ($etudiantsData as $data) {
            $etudiants[] = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'prenom' => $data['prenom'],
                    'nom' => $data['nom'],
                    'no_da' => $data['no_da'],
                    'password' => Hash::make('password'),
                    'role' => 'etudiant',
                    'email_verified_at' => now(),
                ]
            );
        }

        // ─── Classe (section du cours) ────────────────────────────────────────
        /** @var Classe $classe */
        $classe = $cours->classes()->firstOrCreate(
            ['numero' => '00001'],
            [
                'cours_id' => $cours->id,
                'numero' => '00001',
                'code' => $cours->code,
                'nom' => 'Classe 00001',
                'jour_semaine' => 'Lundi',
                'plage_horaire' => '08:30 - 11:30',
            ]
        );

        // Inscrire les étudiants dans la section (classe_etudiant)
        $classe->etudiants()->syncWithoutDetaching(
            collect($etudiants)->mapWithKeys(fn ($e) => [$e->id => ['statut_cours' => 'Actif']])->all()
        );

        // ─── Groupe (équipe dans la section) ──────────────────────────────────
        /** @var Groupe $groupe */
        $groupe = $classe->groupes()->firstOrCreate(
            ['created_by' => $etudiants[0]->id],
            ['classe_id' => $classe->id, 'created_by' => $etudiants[0]->id]
        );

        $groupe->membres()->syncWithoutDetaching(array_map(fn ($e) => $e->id, $etudiants));
        $groupe->thematiques()->syncWithoutDetaching([$thematique->id]);

        // ─── TypeProjet ───────────────────────────────────────────────────────
        /** @var TypeProjet $typeProjet */
        $typeProjet = TypeProjet::firstOrCreate(
            ['enseignant_id' => $prof->id, 'nom' => 'Projet de recherche'],
            [
                'description' => 'Projet de recherche documentaire sur un sujet d\'histoire du Québec.',
                'accessible' => true,
            ]
        );

        // ─── Sections du TypeProjet (idempotent : suppression + recréation) ──
        // La cascade supprime automatiquement les ProjetSectionContenu liés
        $typeProjet->sections()->delete();

        $sectionsData = [
            ['label' => 'Introduction',  'type' => 'texte'],
            ['label' => 'Développement', 'type' => 'paragraphes'],
            ['label' => 'Conclusion',    'type' => 'individuel'],
        ];

        /** @var TypeProjetSection[] $sections */
        $sections = [];
        foreach ($sectionsData as $ordre => $data) {
            $sections[] = TypeProjetSection::create([
                'type_projet_id' => $typeProjet->id,
                'label' => $data['label'],
                'type' => $data['type'],
                'ordre' => $ordre + 1,
            ]);
        }

        // ─── Grille de correction (inlinée — une seule par TypeProjet) ────────
        if (! $typeProjet->grille()->exists()) {
            /** @var GrilleCorrection $grille */
            $grille = GrilleCorrection::create([
                'type_projet_id' => $typeProjet->id,
                'nom' => 'Grille de correction — Projet de recherche',
                'description' => 'Grille officielle du projet de recherche (session en cours). Total : 100 points.',
            ]);

            foreach (self::CRITERES as $ordre => $critere) {
                GrilleCritere::create([
                    'grille_id' => $grille->id,
                    'label' => $critere['label'],
                    'ponderation' => $critere['ponderation'],
                    'ordre' => $ordre + 1,
                ]);
            }

            foreach (self::MALUS as $ordre => $malus) {
                GrilleMalus::create([
                    'grille_id' => $grille->id,
                    'label' => $malus['label'],
                    'deduction' => $malus['deduction'],
                    'description' => $malus['description'],
                    'ordre' => $ordre + 1,
                ]);
            }
        }

        // ─── Projet de recherche ──────────────────────────────────────────────
        /** @var ProjetRecherche $projet */
        $projet = ProjetRecherche::firstOrCreate(
            ['groupe_id' => $groupe->id, 'type_projet_id' => $typeProjet->id],
            ['titre_projet' => 'La Révolution tranquille : rupture ou continuité dans l\'histoire du Québec ?']
        );

        // ─── Contenu de l'introduction (section type 'texte') ────────────────
        ProjetSectionContenu::updateOrCreate(
            ['projet_id' => $projet->id, 'section_id' => $sections[0]->id],
            [
                'contenu' => '<p>Le Québec du début du XX<sup>e</sup> siècle est une société majoritairement rurale, profondément marquée par les valeurs catholiques et une économie dominée par une élite anglophone. Cette période, connue sous le nom de « Grande Noirceur », prend fin avec la mort de Duplessis en 1959 et l\'élection du Parti libéral de Jean Lesage en 1960.</p>'
                    .'<p><strong>Problématique :</strong> Dans quelle mesure la Révolution tranquille constitue-t-elle une véritable rupture avec le passé québécois, et quelles en sont les transformations les plus durables sur le plan social, économique et culturel ?</p>'
                    .'<p>Pour répondre à cette question, nous examinerons d\'abord les réformes institutionnelles, puis les transformations sociales et culturelles, et enfin l\'héritage économique et nationaliste de cette période.</p>',
            ]
        );

        // ─── Paragraphes de développement (section type 'paragraphes') ────────
        $sectionDev = $sections[1];

        // Supprimer et recréer pour idempotence
        ProjetSectionParagraphe::where('projet_id', $projet->id)
            ->where('section_id', $sectionDev->id)
            ->delete();

        $paragraphes = [
            [
                'titre' => 'La réforme de l\'État et la laïcisation des institutions',
                'contenu' => 'La principale transformation de la Révolution tranquille réside dans la récupération par l\'État des pouvoirs jusqu\'alors détenus par l\'Église. La création du ministère de l\'Éducation en 1964, suivant les recommandations de la Commission Parent, marque un tournant décisif : l\'éducation devient une responsabilité de l\'État, gratuite et accessible à tous. La création de la Caisse de dépôt et placement du Québec (1965), de la Régie des rentes (1965) et d\'Hydro-Québec nationalisée (1962) témoignent d\'une volonté de maîtriser les leviers économiques du développement. L\'expression « Maîtres chez nous » du gouvernement Lesage résume parfaitement cette ambition d\'autonomie collective.',
            ],
            [
                'titre' => 'Les transformations sociales et la montée du féminisme',
                'contenu' => 'La Révolution tranquille s\'accompagne d\'une profonde transformation des mentalités et des structures sociales. Le taux de natalité, l\'un des plus élevés au monde dans les années 1950, chute rapidement dans les années 1960, phénomène connu sous le nom de « revanche des berceaux inversée ». Les femmes accèdent massivement au marché du travail et au monde universitaire. Le mouvement féministe québécois s\'affirme avec la création de la Fédération des femmes du Québec en 1966. Les mœurs évoluent, le mariage civil se banalise et le taux de pratique religieuse s\'effondre progressivement.',
            ],
            [
                'titre' => 'L\'affirmation de l\'identité nationale et le mouvement souverainiste',
                'contenu' => 'La Révolution tranquille est indissociable de l\'émergence d\'un nouveau nationalisme québécois. Là où le nationalisme traditionnel était d\'inspiration catholique et conservateur, le néo-nationalisme des années 1960 est laïc, progressiste et axé sur l\'affirmation de la langue française et de la spécificité culturelle québécoise. La fondation du Rassemblement pour l\'indépendance nationale (RIN) en 1960 et du Parti Québécois en 1968 par René Lévesque incarnent cette nouvelle aspiration à la souveraineté.',
            ],
            [
                'titre' => 'Le développement économique et la question linguistique',
                'contenu' => 'Sur le plan économique, la Révolution tranquille amorce une modernisation accélérée du Québec. L\'État investit massivement dans les infrastructures, l\'éducation supérieure et les entreprises publiques. Les Québécois francophones, longtemps cantonnés à des emplois subalternes dans les entreprises anglophones, commencent à accéder à des postes de cadres et de direction. Cette prise de conscience conduit à la Commission Gendron (1968-1972), dont les travaux mèneront à la Loi 22 (1974) puis à la Charte de la langue française, la Loi 101, adoptée en 1977 sous le gouvernement Lévesque.',
            ],
            [
                'titre' => 'L\'héritage de la Révolution tranquille et ses limites',
                'contenu' => 'Si la Révolution tranquille a profondément transformé le Québec, son bilan est nuancé. D\'un côté, elle a permis la création d\'un État moderne, la démocratisation de l\'éducation et l\'affirmation de l\'identité francophone. De l\'autre, la croissance rapide de l\'État a généré une dette publique significative et des bureaucraties parfois inefficaces. La Révolution tranquille reste néanmoins un moment fondateur dans la construction de l\'identité québécoise contemporaine.',
            ],
        ];

        foreach ($paragraphes as $index => $data) {
            ProjetSectionParagraphe::create([
                'projet_id' => $projet->id,
                'section_id' => $sectionDev->id,
                'ordre' => $index + 1,
                'titre' => $data['titre'],
                'contenu' => $data['contenu'],
            ]);
        }

        // ─── Conclusions individuelles (section type 'individuel') ─────────────
        $sectionConclusion = $sections[2];

        $conclusions = [
            $etudiants[0]->id => 'La Révolution tranquille représente, à mes yeux, le moment le plus transformateur de l\'histoire du Québec moderne. En étudiant cette période, j\'ai réalisé à quel point la laïcisation des institutions a libéré la société québécoise d\'un carcan religieux qui freinait son développement. La nationalisation de l\'électricité et la création du ministère de l\'Éducation m\'apparaissent comme les réformes les plus symboliques : elles incarnent la volonté du peuple québécois de reprendre le contrôle de son destin. Ce qui me frappe le plus, c\'est la rapidité de ces transformations — en moins de vingt ans, le Québec a rattrapé un retard historique considérable.',

            $etudiants[1]->id => 'Mon analyse de la Révolution tranquille m\'amène à considérer surtout son impact sur l\'identité nationale. Le passage d\'un nationalisme religieux et conservateur à un nationalisme laïc et progressiste est fascinant. La création du Parti Québécois et l\'adoption de la Loi 101 sont des conséquences directes de cette période. Je pense que sans la Révolution tranquille, la question de la souveraineté du Québec n\'aurait pas pris la forme qu\'elle a connue lors des référendums de 1980 et 1995. Cette période a véritablement redéfini ce que signifie être Québécois au sein du Canada.',

            $etudiants[2]->id => 'En travaillant sur ce projet, c\'est surtout la dimension féministe de la Révolution tranquille qui a retenu mon attention. La transformation du rôle des femmes dans la société québécoise est remarquable : de la ménagère cantonnée au foyer, on passe à des femmes qui investissent l\'université, le marché du travail et la vie politique. La chute du taux de natalité est symptomatique d\'une prise en main par les femmes de leur vie reproductive. Je crois que cet aspect est souvent sous-estimé dans les récits historiques qui se concentrent davantage sur les grandes réformes étatiques et économiques.',

            $etudiants[3]->id => 'Ma réflexion sur la Révolution tranquille porte principalement sur la question des limites de cette transformation. Si les acquis sont indéniables, les travaux d\'historiens comme Ronald Rudin m\'ont conduit à questionner le mythe d\'un avant et après aussi tranché que le narratif officiel le suggère. La croissance de l\'État a certes modernisé le Québec, mais elle a aussi créé des dépendances et des inefficacités bureaucratiques que la société québécoise paie encore aujourd\'hui. La Révolution tranquille est un succès, mais un succès inachevé qui mérite une lecture critique plutôt qu\'une admiration inconditionnelle.',
        ];

        foreach ($conclusions as $userId => $contenu) {
            ProjetConclusion::updateOrCreate(
                ['projet_id' => $projet->id, 'user_id' => $userId, 'section_id' => $sectionConclusion->id],
                ['contenu' => $contenu]
            );
        }

        // ─── TypeProjet "Schéma d'entrevue" ───────────────────────────────────
        // Compatibilité : le TypeProjet peut exister sous l'ancien nom "Entrevue"
        /** @var TypeProjet $typeProjetEntrevue */
        $typeProjetEntrevue = TypeProjet::where('enseignant_id', $prof->id)
            ->whereIn('nom', ["Schéma d'entrevue", 'Entrevue'])
            ->first()
            ?? TypeProjet::create([
                'enseignant_id' => $prof->id,
                'nom' => "Schéma d'entrevue",
                'description' => "Préparation structurée de l'entrevue avec une personne âgée : concepts, dimensions, indicateurs et questions spécifiques.",
                'accessible' => true,
            ]);

        $typeProjetEntrevue->update([
            'nom' => "Schéma d'entrevue",
            'description' => "Préparation structurée de l'entrevue avec une personne âgée : concepts, dimensions, indicateurs et questions spécifiques.",
            'accessible' => true,
        ]);

        // Sections du TypeProjet entrevue (idempotent)
        $typeProjetEntrevue->sections()->delete();

        $sectionsEntrevue = [];
        foreach ([
            ['label' => "Sujet de l'enquête", 'type' => 'texte',    'ordre' => 1],
            ['label' => 'Concepts',            'type' => 'entrevue', 'ordre' => 2],
        ] as $data) {
            $sectionsEntrevue[] = TypeProjetSection::create([
                'type_projet_id' => $typeProjetEntrevue->id,
                'label' => $data['label'],
                'type' => $data['type'],
                'ordre' => $data['ordre'],
            ]);
        }

        // Grille de correction du schéma d'entrevue
        if (! $typeProjetEntrevue->grille()->exists()) {
            /** @var GrilleCorrection $grilleEntrevue */
            $grilleEntrevue = GrilleCorrection::create([
                'type_projet_id' => $typeProjetEntrevue->id,
                'nom' => "Grille de correction — Schéma d'entrevue",
                'description' => "Grille d'évaluation du schéma d'entrevue. Total : 100 points.",
            ]);

            $criteresEntrevue = [
                ['label' => 'Clarté et pertinence du sujet d\'enquête',          'ponderation' => 10],
                ['label' => 'Qualité des dimensions (axes d\'analyse)',           'ponderation' => 20],
                ['label' => 'Pertinence des indicateurs',                         'ponderation' => 20],
                ['label' => 'Qualité et précision des questions (min. 10)',        'ponderation' => 40],
                ['label' => 'Structure logique et cohérence d\'ensemble',          'ponderation' => 5],
                ['label' => 'Respect du nombre minimal de questions',             'ponderation' => 5],
            ];

            foreach ($criteresEntrevue as $ordre => $critere) {
                GrilleCritere::create([
                    'grille_id' => $grilleEntrevue->id,
                    'label' => $critere['label'],
                    'ponderation' => $critere['ponderation'],
                    'ordre' => $ordre + 1,
                ]);
            }
        }

        // Projet schéma d'entrevue pour la classe démo
        /** @var ProjetRecherche $projetEntrevue */
        $projetEntrevue = ProjetRecherche::updateOrCreate(
            ['groupe_id' => $groupe->id, 'type_projet_id' => $typeProjetEntrevue->id],
            ['titre_projet' => 'Entrevue avec un témoin de la Révolution tranquille']
        );

        // Sujet de l'enquête (section type 'texte')
        ProjetSectionContenu::updateOrCreate(
            ['projet_id' => $projetEntrevue->id, 'section_id' => $sectionsEntrevue[0]->id],
            [
                'contenu' => '<p>Notre enquête porte sur le vécu de la Révolution tranquille tel qu\'il a été perçu et vécu par des personnes ayant grandi dans le Québec des années 1950-1970. Nous cherchons à comprendre comment les grandes transformations institutionnelles (laïcisation, nationalisation, essor de l\'État) ont été ressenties au quotidien par la population.</p>',
            ]
        );

        // Concepts d'entrevue (section type 'entrevue') — idempotent
        EntrevueConcept::where('projet_id', $projetEntrevue->id)
            ->where('section_id', $sectionsEntrevue[1]->id)
            ->delete();

        $conceptsData = [
            [
                'label' => 'Pratique religieuse',
                'lignes' => [
                    [
                        'dimension' => 'Fréquence de pratique',
                        'indicateur' => 'Présence à la messe, aux sacrements',
                        'questions' => ['Alliez-vous à la messe tous les dimanches dans votre enfance ?', 'À quel moment avez-vous commencé à aller moins souvent à l\'église ?'],
                    ],
                    [
                        'dimension' => 'Rôle du clergé',
                        'indicateur' => 'Influence du curé dans la vie de quartier',
                        'questions' => ['Le curé de votre paroisse était-il une figure importante dans votre communauté ?', 'Pouvez-vous me donner un exemple de son influence ?'],
                    ],
                ],
            ],
            [
                'label' => "Accès à l'éducation",
                'lignes' => [
                    [
                        'dimension' => 'Scolarisation',
                        'indicateur' => 'Niveau d\'études atteint, accès au collège classique',
                        'questions' => ["Jusqu'à quel niveau avez-vous pu étudier ?", "L'école était-elle gratuite dans votre enfance ?"],
                    ],
                    [
                        'dimension' => 'Création du ministère de l\'Éducation (1964)',
                        'indicateur' => 'Impact perçu sur les opportunités scolaires',
                        'questions' => ['Avez-vous remarqué un changement dans l\'accès à l\'éducation après la réforme Parent ?', 'Vos enfants ont-ils eu plus d\'accès à l\'éducation que vous ?'],
                    ],
                ],
            ],
        ];

        foreach ($conceptsData as $cIndex => $conceptData) {
            $concept = EntrevueConcept::create([
                'projet_id' => $projetEntrevue->id,
                'section_id' => $sectionsEntrevue[1]->id,
                'label' => $conceptData['label'],
                'ordre' => $cIndex + 1,
            ]);

            foreach ($conceptData['lignes'] as $lIndex => $ligneData) {
                EntrevueLigne::create([
                    'concept_id' => $concept->id,
                    'dimension' => $ligneData['dimension'],
                    'indicateur' => $ligneData['indicateur'],
                    'questions' => $ligneData['questions'],
                    'ordre' => $lIndex + 1,
                ]);
            }
        }
    }
}
