<?php

namespace Database\Seeders;

use App\Models\Classe;
use App\Models\Cours;
use App\Models\CoursLienEntrevue;
use App\Models\EntrevueConcept;
use App\Models\EntrevueLigne;
use App\Models\GrilleCorrection;
use App\Models\GrilleCritere;
use App\Models\Groupe;
use App\Models\ProjetConclusion;
use App\Models\ProjetRecherche;
use App\Models\ProjetSectionContenu;
use App\Models\ProjetSectionParagraphe;
use App\Models\Thematique;
use App\Models\TypeProjet;
use App\Models\TypeProjetSection;
use App\Models\TypeProjetTache;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CoursComplementaireDemoSeeder extends Seeder
{
    /**
     * Crée le jeu de données Cours complémentaire : prof3@demo.com, étudiant9–12.
     */
    public function run(): void
    {
        // ─── Enseignant CC ────────────────────────────────────────────────────
        /** @var User $prof */
        $prof = User::updateOrCreate(
            ['email' => 'prof3@demo.com'],
            [
                'prenom' => 'Isabelle',
                'nom' => 'Lefebvre',
                'password' => Hash::make('password'),
                'role' => 'enseignant',
                'email_verified_at' => now(),
            ]
        );

        // ─── Thématique ───────────────────────────────────────────────────────
        /** @var Thematique $thematique */
        $thematique = Thematique::firstOrCreate(
            ['nom' => 'Les droits civiques aux États-Unis', 'enseignant_id' => $prof->id],
            [
                'description' => 'Mouvement pour les droits civiques : déségrégation, Martin Luther King et l\'héritage des années 1960.',
                'periode_historique' => '1955 – 1968',
                'enseignant_id' => $prof->id,
            ]
        );

        // ─── Cours complémentaire ─────────────────────────────────────────────
        /** @var Cours $cours */
        $cours = Cours::firstOrCreate(
            ['code' => '330-CC-01', 'enseignant_id' => $prof->id],
            [
                'nom_cours' => 'Société américaine — Cours complémentaire Démo',
                'description' => 'Cours de démonstration pour le niveau Cours complémentaire.',
                'groupe' => '00001',
                'annee' => 2026,
                'session' => 'hiver',
                'enseignant_id' => $prof->id,
                'type_cours' => 'cours_complementaire',
            ]
        );

        $cours->update(['type_cours' => 'cours_complementaire']);

        // ─── Étudiants ────────────────────────────────────────────────────────
        $etudiantsData = [
            ['prenom' => 'Chloé',   'nom' => 'Moreau',   'no_da' => 'DA100009', 'email' => 'etudiant9@demo.com'],
            ['prenom' => 'Antoine', 'nom' => 'Simon',    'no_da' => 'DA100010', 'email' => 'etudiant10@demo.com'],
            ['prenom' => 'Manon',   'nom' => 'Laurent',  'no_da' => 'DA100011', 'email' => 'etudiant11@demo.com'],
            ['prenom' => 'Florian', 'nom' => 'Michel',   'no_da' => 'DA100012', 'email' => 'etudiant12@demo.com'],
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

        // ─── Classe ───────────────────────────────────────────────────────────
        /** @var Classe $classe */
        $classe = $cours->classes()->firstOrCreate(
            ['numero' => '00001'],
            [
                'cours_id' => $cours->id,
                'numero' => '00001',
                'code' => $cours->code,
                'nom' => 'Classe CC 00001',
                'jour_semaine' => 'Jeudi',
                'plage_horaire' => '09:00 - 12:00',
            ]
        );

        $classe->etudiants()->syncWithoutDetaching(
            collect($etudiants)->mapWithKeys(fn ($e) => [$e->id => ['statut_cours' => 'Actif']])->all()
        );

        // ─── Groupe ───────────────────────────────────────────────────────────
        /** @var Groupe $groupe */
        $groupe = $classe->groupes()->firstOrCreate(
            ['created_by' => $etudiants[0]->id],
            ['classe_id' => $classe->id, 'created_by' => $etudiants[0]->id]
        );

        $groupe->membres()->syncWithoutDetaching(array_map(fn ($e) => $e->id, $etudiants));
        $groupe->thematiques()->syncWithoutDetaching([$thematique->id]);

        // ─── TypeProjet Cours complémentaire ──────────────────────────────────
        /** @var TypeProjet $typeProjet */
        $typeProjet = TypeProjet::firstOrCreate(
            ['cours_id' => $cours->id, 'nom' => 'Essai argumentatif'],
            [
                'cours_id' => $cours->id,
                'enseignant_id' => $cours->enseignant_id,
                'description' => 'Rédaction d\'un essai argumentatif sur un enjeu du mouvement des droits civiques.',
                'accessible' => true,
            ]
        );

        // Sections
        $typeProjet->sections()->delete();

        $sectionsData = [
            ['label' => 'Introduction et thèse',           'type' => 'texte'],
            ['label' => 'Arguments',                        'type' => 'paragraphes'],
            ['label' => 'Schéma d\'entrevue',               'type' => 'entrevue'],
            ['label' => 'Tâches de l\'équipe',              'type' => 'tache'],
            ['label' => 'Conclusion',                       'type' => 'individuel'],
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

        // Grille de correction
        if (! $typeProjet->grille()->exists()) {
            /** @var GrilleCorrection $grille */
            $grille = GrilleCorrection::create([
                'type_projet_id' => $typeProjet->id,
                'nom' => 'Grille — Essai argumentatif',
                'description' => 'Évaluation de l\'essai argumentatif. Total : 100 points.',
            ]);

            $criteres = [
                ['label' => 'Clarté et originalité de la thèse',              'ponderation' => 20],
                ['label' => 'Pertinence et développement des arguments',       'ponderation' => 40],
                ['label' => 'Utilisation des sources historiques',             'ponderation' => 20],
                ['label' => 'Qualité de la langue et structure de l\'essai',   'ponderation' => 20],
            ];

            foreach ($criteres as $ordre => $critere) {
                GrilleCritere::create([
                    'grille_id' => $grille->id,
                    'label' => $critere['label'],
                    'ponderation' => $critere['ponderation'],
                    'ordre' => $ordre + 1,
                ]);
            }
        }

        // ─── Tâches du TypeProjet ─────────────────────────────────────────────
        $tachesSection = $typeProjet->sections()->where('type', 'tache')->first();
        if ($tachesSection && $typeProjet->taches()->count() === 0) {
            $tachesData = [
                ['titre' => 'Rechercher des sources primaires sur le mouvement', 'description' => 'Trouver au moins 3 sources primaires (discours, lettres, photographies).'],
                ['titre' => 'Préparer les questions d\'entrevue', 'description' => 'Rédiger la question principale et 6 à 8 sous-questions.'],
                ['titre' => 'Rédiger l\'introduction et la thèse', 'description' => null],
                ['titre' => 'Développer les arguments', 'description' => 'Minimum 3 arguments distincts avec sources.'],
                ['titre' => 'Rédiger la conclusion individuelle', 'description' => null],
            ];

            foreach ($tachesData as $ordre => $tache) {
                TypeProjetTache::create([
                    'type_projet_id' => $typeProjet->id,
                    'titre' => $tache['titre'],
                    'description' => $tache['description'],
                    'ordre' => $ordre + 1,
                ]);
            }
        }

        // ─── Liens d'entrevue du cours ────────────────────────────────────────
        if ($cours->liensEntrevue()->count() === 0) {
            $liensData = [
                ['label' => 'Guide de l\'entrevue historique — Bibliothèque nationale', 'url' => 'https://www.bnf.fr/fr/les-sources-orales-en-histoire'],
                ['label' => 'Conseils pour mener une entrevue — Musée canadien de l\'histoire', 'url' => 'https://www.museedelhistoire.ca'],
                ['label' => 'Exemples de questions d\'entrevue sur les droits civiques (PDF)', 'url' => 'https://example.com/droits-civiques-guide.pdf'],
            ];

            foreach ($liensData as $ordre => $lien) {
                CoursLienEntrevue::create([
                    'cours_id' => $cours->id,
                    'label' => $lien['label'],
                    'url' => $lien['url'],
                    'ordre' => $ordre + 1,
                ]);
            }
        }

        // ─── Projet ───────────────────────────────────────────────────────────
        /** @var ProjetRecherche $projet */
        $projet = ProjetRecherche::firstOrCreate(
            ['groupe_id' => $groupe->id, 'type_projet_id' => $typeProjet->id],
            ['titre_projet' => 'Le rôle de Martin Luther King dans la déségrégation scolaire aux États-Unis']
        );

        // ─── Introduction et thèse (texte) ────────────────────────────────────
        ProjetSectionContenu::updateOrCreate(
            ['projet_id' => $projet->id, 'section_id' => $sections[0]->id],
            ['contenu' => '<p>Les États-Unis des années 1950 sont une société profondément divisée par la ségrégation raciale institutionnalisée. Dans les États du Sud notamment, les lois Jim Crow perpétuent une discrimination systémique qui touche tous les aspects de la vie quotidienne : transports, restaurants, hôpitaux et surtout écoles. La décision de la Cour suprême dans l\'affaire <em>Brown v. Board of Education</em> (1954) déclare la ségrégation scolaire inconstitutionnelle, mais son application reste très inégale et contestée.</p>'
                .'<p><strong>Thèse :</strong> Martin Luther King Jr. a joué un rôle décisif dans la déségrégation scolaire aux États-Unis non pas par l\'action judiciaire directe, mais en mobilisant l\'opinion publique nationale et internationale grâce à sa stratégie de désobéissance civile non-violente, rendant politiquement intenable le maintien d\'un système ségrégationniste explicitement raciste.</p>']
        );

        // ─── Arguments (paragraphes) ──────────────────────────────────────────
        ProjetSectionParagraphe::where('projet_id', $projet->id)
            ->where('section_id', $sections[1]->id)
            ->delete();

        $arguments = [
            [
                'titre' => 'La stratégie de désobéissance civile non-violente comme levier de changement',
                'contenu' => 'Martin Luther King Jr. a forgé une stratégie politique unique en son genre, inspirée de Gandhi et des principes chrétiens de la non-violence. En orchestrant des boycotts, des marches et des sit-ins pacifiques, il forçait les autorités ségrégationnistes à réprimer des manifestants pacifiques devant les caméras de télévision nationales. Les images des affrontements de Birmingham (1963) — policiers utilisant lances à eau et chiens contre des manifestants non-armés — ont choqué la conscience américaine et mondiale, rendant politiquement coûteux le maintien du statu quo.',
            ],
            [
                'titre' => 'L\'impact du discours « I Have a Dream » sur l\'opinion publique',
                'contenu' => 'Le discours prononcé le 28 août 1963 lors de la Marche sur Washington constitue un tournant rhétorique et politique majeur. En articulant sa vision d\'une Amérique où les enfants noirs et blancs joueraient ensemble, King transformait une revendication de droits civiques en appel universel aux valeurs fondatrices américaines. Ce discours a contribué à créer la pression politique nécessaire à l\'adoption du Civil Rights Act de 1964, qui interdisait toute discrimination dans les établissements publics, y compris les écoles.',
            ],
            [
                'titre' => 'Les limites et les critiques de son action sur la question scolaire',
                'contenu' => 'Si l\'action de King a été décisive pour faire avancer la législation fédérale, la déségrégation effective des écoles s\'est heurtée à d\'importantes résistances locales. Le busing — transport d\'élèves noirs vers des écoles blanches et vice-versa — a suscité des controverses même dans des États du Nord comme Boston. Des historiens comme Herbert Kohl ont souligné que King n\'a pas résolu la question des inégalités structurelles dans le financement des écoles, qui reste un problème persistant aux États-Unis aujourd\'hui.',
            ],
        ];

        foreach ($arguments as $index => $data) {
            ProjetSectionParagraphe::create([
                'projet_id' => $projet->id,
                'section_id' => $sections[1]->id,
                'ordre' => $index + 1,
                'titre' => $data['titre'],
                'contenu' => $data['contenu'],
            ]);
        }

        // ─── Schéma d'entrevue (entrevue) — idempotent ────────────────────────
        EntrevueConcept::where('projet_id', $projet->id)
            ->where('section_id', $sections[2]->id)
            ->delete();

        $conceptsData = [
            [
                'label' => 'Discrimination scolaire vécue',
                'lignes' => [
                    [
                        'dimension' => 'Expérience personnelle',
                        'indicateur' => 'Fréquentation d\'une école ségréguée',
                        'questions' => [
                            'Avez-vous fréquenté une école ségréguée dans votre enfance ou adolescence ?',
                            'Pouvez-vous décrire les conditions dans votre école comparativement aux écoles pour Blancs ?',
                        ],
                    ],
                    [
                        'dimension' => 'Perception du changement',
                        'indicateur' => 'Ressenti lors de la déségrégation',
                        'questions' => [
                            'Comment avez-vous vécu l\'intégration scolaire dans votre communauté ?',
                            'Y avait-il des tensions ou des résistances de la part de certains parents ou enseignants ?',
                        ],
                    ],
                ],
            ],
            [
                'label' => 'Influence de Martin Luther King',
                'lignes' => [
                    [
                        'dimension' => 'Connaissance et admiration',
                        'indicateur' => 'Perception de King à l\'époque',
                        'questions' => [
                            'Comment perceviez-vous Martin Luther King dans les années 1960 ?',
                            'Ses discours ou ses actions vous ont-ils personnellement inspiré(e) ?',
                        ],
                    ],
                    [
                        'dimension' => 'Impact local',
                        'indicateur' => 'Répercussions dans votre communauté',
                        'questions' => [
                            'Y a-t-il eu des manifestations ou des actions militantes dans votre ville ou quartier ?',
                            'Connaissez-vous des personnes qui ont participé aux marches pour les droits civiques ?',
                        ],
                    ],
                ],
            ],
        ];

        foreach ($conceptsData as $cIndex => $conceptData) {
            $concept = EntrevueConcept::create([
                'projet_id' => $projet->id,
                'section_id' => $sections[2]->id,
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

        // ─── Conclusions individuelles (individuel) ────────────────────────────
        $conclusionsData = [
            $etudiants[0]->id => 'En travaillant sur cet essai, j\'ai pris conscience que la lutte pour la déségrégation scolaire n\'est pas un chapitre clos de l\'histoire américaine. Si les lois ont changé, les inégalités structurelles dans le financement des écoles — largement déterminé par la richesse des quartiers — perpétuent des disparités éducatives qui frappent encore majoritairement les communautés afro-américaines. L\'héritage de King m\'inspire à voir dans l\'éducation non seulement un droit, mais un vecteur de justice sociale qui exige une vigilance constante.',

            $etudiants[1]->id => 'Ma recherche sur Martin Luther King m\'a révélé une figure bien plus nuancée que l\'icône figée dans les manuels scolaires. King a dû naviguer entre des courants politiques contradictoires : les partisans de l\'action directe radicale (comme le Black Power de Stokely Carmichael) et les modérés qui lui reprochaient d\'aller trop loin. Sa capacité à maintenir une coalition large tout en restant fidèle à ses principes de non-violence constitue selon moi sa contribution la plus durable à la pensée politique américaine.',

            $etudiants[2]->id => 'Ce qui m\'a le plus frappée dans mes recherches, c\'est la résistance acharnée que la déségrégation scolaire a rencontrée non seulement dans le Sud, mais dans l\'ensemble du pays. Les émeutes de Boston en 1974 contre le busing montrent que le racisme n\'était pas une spécificité sudiste. L\'entrevue que nous avons menée avec Mme Richardson, ancienne enseignante afro-américaine de Chicago, a illustré concrètement ces tensions : elle se souvient que même ses collègues blancs progressistes manifestaient des réticences à l\'intégration dans les années 1960.',

            $etudiants[3]->id => 'La lecture de <em>Letter from Birmingham Jail</em> de King m\'a particulièrement touché. Sa réponse aux religieux blancs qui l\'accusaient d\'être trop impatient révèle une intelligence politique et morale rare. King y démontre que l\'injustice ne se corrige pas en attendant « le bon moment » : chaque génération doit prendre ses responsabilités face aux inégalités de son époque. C\'est un message qui résonne encore aujourd\'hui dans les débats sur l\'équité éducative et la réforme des systèmes scolaires.',
        ];

        foreach ($conclusionsData as $userId => $contenu) {
            ProjetConclusion::updateOrCreate(
                ['projet_id' => $projet->id, 'user_id' => $userId, 'section_id' => $sections[4]->id],
                ['contenu' => $contenu]
            );
        }
    }
}
