<?php

namespace Database\Seeders;

use App\Models\Classe;
use App\Models\Cours;
use App\Models\GrilleCorrection;
use App\Models\GrilleCritere;
use App\Models\Groupe;
use App\Models\ProjetQuestionChoisie;
use App\Models\ProjetRecherche;
use App\Models\ProjetSchemaVisuel;
use App\Models\ProjetSectionContenu;
use App\Models\QuestionBanque;
use App\Models\Thematique;
use App\Models\TypeProjet;
use App\Models\TypeProjetSection;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DepDemoSeeder extends Seeder
{
    /**
     * Crée le jeu de données DEP : prof2@demo.com, étudiant5–8, cours DEP,
     * 4 TypeProjets (Schéma visuel placeholder, Construction de questions, L'entrevue, Introspection).
     */
    public function run(): void
    {
        // ─── Enseignant DEP ───────────────────────────────────────────────────
        /** @var User $prof */
        $prof = User::updateOrCreate(
            ['email' => 'prof2@demo.com'],
            [
                'prenom' => 'Martin',
                'nom' => 'Côté',
                'password' => Hash::make('password'),
                'role' => 'enseignant',
                'email_verified_at' => now(),
            ]
        );

        // ─── Thématique ───────────────────────────────────────────────────────
        /** @var Thematique $thematique */
        $thematique = Thematique::firstOrCreate(
            ['nom' => 'La Seconde Guerre mondiale', 'enseignant_id' => $prof->id],
            [
                'description' => 'Étude des causes, du déroulement et des conséquences de la Seconde Guerre mondiale.',
                'periode_historique' => '1939 – 1945',
                'enseignant_id' => $prof->id,
            ]
        );

        // ─── Cours DEP ────────────────────────────────────────────────────────
        /** @var Cours $cours */
        $cours = Cours::firstOrCreate(
            ['code' => '330-DEP-01', 'enseignant_id' => $prof->id],
            [
                'nom_cours' => 'Histoire mondiale — DEP Démo',
                'description' => 'Cours de démonstration pour le niveau DEP.',
                'groupe' => '00001',
                'enseignant_id' => $prof->id,
                'type_cours' => 'dep',
            ]
        );

        $cours->update(['type_cours' => 'dep']);

        // ─── Étudiants ────────────────────────────────────────────────────────
        $etudiantsData = [
            ['prenom' => 'Anaïs',    'nom' => 'Leblanc',  'no_da' => 'DA100005', 'email' => 'etudiant5@demo.com'],
            ['prenom' => 'Théo',     'nom' => 'Girard',   'no_da' => 'DA100006', 'email' => 'etudiant6@demo.com'],
            ['prenom' => 'Juliette', 'nom' => 'Bernard',  'no_da' => 'DA100007', 'email' => 'etudiant7@demo.com'],
            ['prenom' => 'Raphaël',  'nom' => 'Dupont',   'no_da' => 'DA100008', 'email' => 'etudiant8@demo.com'],
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
                'nom' => 'Classe DEP 00001',
                'jour_semaine' => 'Mardi',
                'plage_horaire' => '13:00 - 16:00',
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

        // ─── TypeProjet DEP 1 — Schéma visuel (placeholder) ──────────────────
        $this->creerTypeProjetSchemaVisuel($cours, $groupe);

        // ─── TypeProjet DEP 2 — Construction de questions d'entrevue ─────────
        $this->creerTypeProjetConstructionQuestions($cours, $prof, $groupe);

        // ─── TypeProjet DEP 3 — L'entrevue ───────────────────────────────────
        $this->creerTypeProjetEntrevue($cours, $groupe);

        // ─── TypeProjet DEP 4 — Introspection ────────────────────────────────
        $this->creerTypeProjetIntrospection($cours, $groupe);
    }

    /**
     * DEP 1 — Schéma visuel (causes / activités / conséquences, drag-and-drop).
     */
    private function creerTypeProjetSchemaVisuel(Cours $cours, Groupe $groupe): void
    {
        /** @var TypeProjet $typeProjet */
        $typeProjet = TypeProjet::firstOrCreate(
            ['cours_id' => $cours->id, 'nom' => 'Schéma visuel'],
            [
                'cours_id' => $cours->id,
                'enseignant_id' => $cours->enseignant_id,
                'description' => 'Représentation visuelle des causes, activités et conséquences de l\'événement étudié.',
                'accessible' => true,
            ]
        );

        $typeProjet->sections()->delete();

        $section = TypeProjetSection::create([
            'type_projet_id' => $typeProjet->id,
            'label' => 'Schéma visuel',
            'type' => 'schema_visuel',
            'ordre' => 1,
            'description' => 'Placez les cartes dans les zones Causes, Activités et Conséquences. Vous pouvez les déplacer librement entre les zones.',
        ]);

        /** @var ProjetRecherche $projet */
        $projet = ProjetRecherche::firstOrCreate(
            ['groupe_id' => $groupe->id, 'type_projet_id' => $typeProjet->id],
            ['titre_projet' => 'Schéma visuel — Seconde Guerre mondiale']
        );

        // ─── Contenu de démonstration du schéma visuel ───────────────────────
        ProjetSchemaVisuel::updateOrCreate(
            ['projet_id' => $projet->id, 'section_id' => $section->id],
            ['contenu' => [
                'image_centrale' => null,
                'zones' => [
                    'causes' => [
                        ['id' => 'c1', 'texte' => 'Montée des nationalismes en Europe', 'image' => null],
                        ['id' => 'c2', 'texte' => 'Traité de Versailles et ressentiments allemands', 'image' => null],
                        ['id' => 'c3', 'texte' => 'Crise économique de 1929 et instabilité politique', 'image' => null],
                        ['id' => 'c4', 'texte' => 'Idéologie nazie et expansion hitlérienne', 'image' => null],
                    ],
                    'activites' => [
                        ['id' => 'a1', 'texte' => 'Blitzkrieg : guerre éclair sur le front ouest (1940)', 'image' => null],
                        ['id' => 'a2', 'texte' => 'Bataille d\'Angleterre et bombardements (1940–1941)', 'image' => null],
                        ['id' => 'a3', 'texte' => 'Opération Barbarossa : invasion de l\'URSS (1941)', 'image' => null],
                        ['id' => 'a4', 'texte' => 'Débarquement en Normandie — Jour J (6 juin 1944)', 'image' => null],
                    ],
                    'consequences' => [
                        ['id' => 'co1', 'texte' => '50 à 70 millions de victimes militaires et civiles', 'image' => null],
                        ['id' => 'co2', 'texte' => 'Création de l\'ONU (1945) et nouvel ordre mondial', 'image' => null],
                        ['id' => 'co3', 'texte' => 'Début de la Guerre froide entre les États-Unis et l\'URSS', 'image' => null],
                        ['id' => 'co4', 'texte' => 'Plan Marshall et reconstruction de l\'Europe occidentale', 'image' => null],
                    ],
                ],
            ]]
        );
    }

    /**
     * DEP 2 — Construction de questions d'entrevue (banque de questions + choix_questions).
     */
    private function creerTypeProjetConstructionQuestions(Cours $cours, User $prof, Groupe $groupe): void
    {
        /** @var TypeProjet $typeProjet */
        $typeProjet = TypeProjet::firstOrCreate(
            ['cours_id' => $cours->id, 'nom' => 'Construction de questions d\'entrevue'],
            [
                'cours_id' => $cours->id,
                'enseignant_id' => $cours->enseignant_id,
                'description' => 'L\'équipe choisit les questions qu\'elle souhaite poser lors de l\'entrevue avec le témoin.',
                'accessible' => true,
            ]
        );

        $typeProjet->sections()->delete();

        /** @var TypeProjetSection $sectionIntro */
        $sectionIntro = TypeProjetSection::create([
            'type_projet_id' => $typeProjet->id,
            'label' => 'Consignes de préparation',
            'type' => 'texte',
            'ordre' => 1,
            'description' => 'Lisez les consignes avant de sélectionner vos questions.',
        ]);

        /** @var TypeProjetSection $sectionQuestions */
        $sectionQuestions = TypeProjetSection::create([
            'type_projet_id' => $typeProjet->id,
            'label' => 'Choix des questions d\'entrevue',
            'type' => 'choix_questions',
            'ordre' => 2,
            'description' => 'Sélectionnez les questions que vous poserez au témoin (minimum 5, maximum 10).',
        ]);

        // Banque de questions
        $questionsData = [
            'Où étiez-vous et que faisiez-vous au moment du déclenchement de la guerre ?',
            'Comment avez-vous appris le début des hostilités ?',
            'Quels souvenirs gardez-vous du quotidien pendant la guerre ?',
            'Avez-vous vécu des privations ou des rationnements ? Pouvez-vous en parler ?',
            'Comment la guerre a-t-elle affecté votre famille ?',
            'Avez-vous perdu des proches durant le conflit ?',
            'Comment viviez-vous la propagande et les nouvelles de l\'époque ?',
            'Quels événements de la guerre vous ont le plus marqué(e) ?',
            'Comment avez-vous vécu la fin de la guerre et la Libération ?',
            'Quel message souhaiteriez-vous transmettre aux jeunes générations sur cette période ?',
        ];

        foreach ($questionsData as $ordre => $contenu) {
            QuestionBanque::create([
                'section_id' => $sectionQuestions->id,
                'contenu' => $contenu,
                'ordre' => $ordre + 1,
            ]);
        }

        // Grille de correction
        if (! $typeProjet->grille()->exists()) {
            /** @var GrilleCorrection $grille */
            $grille = GrilleCorrection::create([
                'type_projet_id' => $typeProjet->id,
                'nom' => 'Grille — Construction de questions d\'entrevue',
                'description' => 'Évaluation de la pertinence et de la qualité des questions sélectionnées.',
            ]);

            $criteres = [
                ['label' => 'Pertinence des questions choisies par rapport à la thématique', 'ponderation' => 35],
                ['label' => 'Variété et complémentarité des questions',                      'ponderation' => 30],
                ['label' => 'Qualité de la rédaction et clarté des questions',               'ponderation' => 20],
                ['label' => 'Respect des consignes (nombre de questions)',                   'ponderation' => 15],
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

        /** @var ProjetRecherche $projet */
        $projet = ProjetRecherche::firstOrCreate(
            ['groupe_id' => $groupe->id, 'type_projet_id' => $typeProjet->id],
            ['titre_projet' => 'Questions d\'entrevue sur la Seconde Guerre mondiale']
        );

        // ─── Contenu de la section intro (consignes) ─────────────────────────
        ProjetSectionContenu::updateOrCreate(
            ['projet_id' => $projet->id, 'section_id' => $sectionIntro->id],
            ['contenu' => '<p>Lisez attentivement la liste de questions disponibles et sélectionnez celles que vous souhaitez poser au témoin lors de l\'entrevue. Vous devez choisir <strong>au minimum 5 questions</strong> et <strong>au maximum 10</strong>. Assurez-vous de couvrir différents aspects de l\'expérience vécue pendant la Seconde Guerre mondiale : avant-guerre, quotidien, impact familial et message pour les générations futures.</p>']
        );

        // ─── Questions choisies (5 parmi les 10 de la banque) ────────────────
        ProjetQuestionChoisie::where('projet_id', $projet->id)
            ->where('section_id', $sectionQuestions->id)
            ->delete();

        $banqueIds = $sectionQuestions->questionsBanque()->orderBy('ordre')->pluck('id')->toArray();

        foreach ([0, 2, 4, 7, 9] as $index) {
            if (isset($banqueIds[$index])) {
                ProjetQuestionChoisie::create([
                    'projet_id' => $projet->id,
                    'section_id' => $sectionQuestions->id,
                    'question_banque_id' => $banqueIds[$index],
                ]);
            }
        }
    }

    /**
     * DEP 3 — L'entrevue (texte de préparation + section vidéo pour l'enregistrement filmé).
     */
    private function creerTypeProjetEntrevue(Cours $cours, Groupe $groupe): void
    {
        /** @var TypeProjet $typeProjet */
        $typeProjet = TypeProjet::firstOrCreate(
            ['cours_id' => $cours->id, 'nom' => 'L\'entrevue'],
            [
                'cours_id' => $cours->id,
                'enseignant_id' => $cours->enseignant_id,
                'description' => 'Réalisation et dépôt de l\'entrevue filmée avec le témoin.',
                'accessible' => true,
            ]
        );

        $typeProjet->sections()->delete();

        $sectionPrep = TypeProjetSection::create([
            'type_projet_id' => $typeProjet->id,
            'label' => 'Préparation et consentement',
            'type' => 'texte',
            'ordre' => 1,
            'description' => 'Décrivez comment vous avez préparé et conduit l\'entrevue. Confirmez que le consentement du témoin a été obtenu.',
        ]);

        TypeProjetSection::create([
            'type_projet_id' => $typeProjet->id,
            'label' => 'Enregistrement vidéo de l\'entrevue',
            'type' => 'video',
            'ordre' => 2,
            'description' => 'Déposez la vidéo de l\'entrevue (fichier ou lien URL).',
        ]);

        // Grille de correction
        if (! $typeProjet->grille()->exists()) {
            /** @var GrilleCorrection $grille */
            $grille = GrilleCorrection::create([
                'type_projet_id' => $typeProjet->id,
                'nom' => 'Grille — L\'entrevue',
                'description' => 'Évaluation de la conduite et de la qualité de l\'entrevue.',
            ]);

            $criteres = [
                ['label' => 'Qualité des questions posées et écoute active',              'ponderation' => 30],
                ['label' => 'Pertinence des relances et approfondissement des réponses',  'ponderation' => 25],
                ['label' => 'Clarté et structure de l\'entrevue',                        'ponderation' => 25],
                ['label' => 'Qualité technique de l\'enregistrement',                    'ponderation' => 20],
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

        /** @var ProjetRecherche $projet */
        $projet = ProjetRecherche::firstOrCreate(
            ['groupe_id' => $groupe->id, 'type_projet_id' => $typeProjet->id],
            ['titre_projet' => 'Entrevue avec un témoin de la Seconde Guerre mondiale']
        );

        // ─── Texte de préparation ─────────────────────────────────────────────
        ProjetSectionContenu::updateOrCreate(
            ['projet_id' => $projet->id, 'section_id' => $sectionPrep->id],
            ['contenu' => '<p>Notre équipe a pris contact avec M. Jean-Pierre Villeneuve, 87 ans, ancien combattant ayant participé à la campagne d\'Italie (1943–1945). Nous l\'avons rejoint par l\'intermédiaire du centre communautaire local, qui nous a mis en relation avec sa famille. Une rencontre préliminaire a eu lieu le 28 octobre 2026 afin de lui présenter le projet et de recueillir son consentement éclairé.</p>'
                .'<p>L\'entrevue s\'est déroulée à son domicile le 5 novembre 2026 et a duré environ 75 minutes. Nous avons utilisé un enregistreur numérique avec son accord. L\'atmosphère était détendue, ce qui a favorisé le partage de souvenirs personnels touchants et détaillés.</p>']
        );
    }

    /**
     * DEP 4 — Introspection (questions réflexives + enregistrement audio ou vidéo).
     */
    private function creerTypeProjetIntrospection(Cours $cours, Groupe $groupe): void
    {
        /** @var TypeProjet $typeProjet */
        $typeProjet = TypeProjet::firstOrCreate(
            ['cours_id' => $cours->id, 'nom' => 'Introspection'],
            [
                'cours_id' => $cours->id,
                'enseignant_id' => $cours->enseignant_id,
                'description' => 'Réflexion personnelle sur l\'expérience d\'entrevue et sur l\'apprentissage réalisé.',
                'accessible' => true,
            ]
        );

        $typeProjet->sections()->delete();

        $sectionReflexion = TypeProjetSection::create([
            'type_projet_id' => $typeProjet->id,
            'label' => 'Réflexion écrite',
            'type' => 'texte',
            'ordre' => 1,
            'description' => 'Répondez aux questions réflexives : Qu\'avez-vous appris de cette rencontre ? Comment ce témoignage éclaire-t-il votre compréhension de la Seconde Guerre mondiale ?',
        ]);

        TypeProjetSection::create([
            'type_projet_id' => $typeProjet->id,
            'label' => 'Enregistrement audio ou vidéo (optionnel)',
            'type' => 'audio',
            'ordre' => 2,
            'description' => 'Partagez votre réflexion à l\'oral si vous le souhaitez.',
        ]);

        // Grille de correction
        if (! $typeProjet->grille()->exists()) {
            /** @var GrilleCorrection $grille */
            $grille = GrilleCorrection::create([
                'type_projet_id' => $typeProjet->id,
                'nom' => 'Grille — Introspection',
                'description' => 'Évaluation de la profondeur de la réflexion personnelle.',
            ]);

            $criteres = [
                ['label' => 'Profondeur et authenticité de la réflexion',                    'ponderation' => 40],
                ['label' => 'Liens établis avec les apprentissages du cours',                'ponderation' => 35],
                ['label' => 'Clarté de l\'expression (écrite ou orale)',                     'ponderation' => 25],
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

        /** @var ProjetRecherche $projet */
        $projet = ProjetRecherche::firstOrCreate(
            ['groupe_id' => $groupe->id, 'type_projet_id' => $typeProjet->id],
            ['titre_projet' => 'Introspection — Ce que l\'entrevue m\'a appris']
        );

        // ─── Texte de réflexion ───────────────────────────────────────────────
        ProjetSectionContenu::updateOrCreate(
            ['projet_id' => $projet->id, 'section_id' => $sectionReflexion->id],
            ['contenu' => '<p>Cette rencontre avec M. Villeneuve a profondément transformé ma compréhension de la Seconde Guerre mondiale. Avant l\'entrevue, j\'appréhendais ce conflit principalement à travers des statistiques et des cartes de batailles. Entendre le récit d\'une personne qui a vécu les horreurs des tranchées italiennes, qui a perdu des amis proches, et qui a survécu à des conditions inimaginables a rendu l\'histoire vivante d\'une manière que les manuels ne peuvent pas reproduire.</p>'
                .'<p>Ce témoignage m\'a également sensibilisé à l\'importance de la mémoire orale. M. Villeneuve appartient à une génération dont les représentants sont de plus en plus rares. Son récit constitue un patrimoine précieux qui risque de disparaître avec lui. Cette expérience m\'a convaincu de l\'importance de préserver ces témoignages pour les générations futures, car aucune reconstitution fictive ne peut rivaliser avec la vérité brute d\'un témoin direct.</p>']
        );
    }
}
