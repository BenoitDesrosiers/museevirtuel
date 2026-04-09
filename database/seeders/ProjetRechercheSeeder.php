<?php

namespace Database\Seeders;

use App\Models\Groupe;
use App\Models\ProjetConclusion;
use App\Models\ProjetRecherche;
use App\Models\ProjetSectionContenu;
use App\Models\ProjetSectionParagraphe;
use App\Models\TypeProjet;
use Illuminate\Database\Seeder;

class ProjetRechercheSeeder extends Seeder
{
    /**
     * Remplit le premier groupe trouvé avec un projet fictif (pizza)
     * en utilisant le TypeProjet existant de l'enseignant qui possède des sections.
     * Sections attendues : Sujet amené · Sujet posé · Sujet divisé · Développement · Conclusion.
     */
    public function run(): void
    {
        $groupe = Groupe::with(['classe.enseignant', 'membres'])->first();

        if (! $groupe) {
            $this->command->warn('Aucun groupe trouvé. Lance d\'abord GroupeDemoSeeder.');

            return;
        }

        $enseignant = $groupe->classe->enseignant;

        // ─── TypeProjet existant avec sections ────────────────────────────────
        /** @var TypeProjet|null $typeProjet */
        $typeProjet = TypeProjet::where('enseignant_id', $enseignant->id)
            ->whereHas('sections')
            ->with(['sections' => fn ($q) => $q->orderBy('ordre')])
            ->first();

        if (! $typeProjet) {
            $this->command->warn('Aucun TypeProjet avec sections trouvé. Lance les migrations d\'abord.');

            return;
        }

        $sections = $typeProjet->sections->keyBy('label');

        // ─── Projet de recherche ──────────────────────────────────────────────
        /** @var ProjetRecherche $projet */
        $projet = ProjetRecherche::firstOrCreate(
            ['groupe_id' => $groupe->id, 'type_projet_id' => $typeProjet->id],
            ['titre_projet' => 'L\'impact des pizzas froides sur la motivation académique des étudiants en fin de session'],
        );

        // ─── Contenus des sections texte (Amené / Posé / Divisé) ─────────────
        $contenusSections = [
            'Sujet amené' => '<p>Depuis l\'aube de la civilisation universitaire, l\'être humain a cherché à comprendre les mécanismes profonds qui gouvernent sa capacité à rédiger un travail de fin de session la veille de la remise. De nombreux philosophes, dont Socrate lui-même (qui, rappelons-le, n\'avait pas accès à Internet), se sont penchés sur cette question fondamentale : pourquoi est-ce que tout devient urgent en décembre ?</p><p>La pizza froide, vestige sacré des nuits de travail, s\'impose comme un objet d\'étude incontournable dans ce contexte de détresse intellectuelle organisée.</p>',
            'Sujet posé' => '<p>La problématique centrale de cette recherche peut être formulée comme suit : <strong>dans quelle mesure la consommation de pizza froide à 3h du matin influence-t-elle la qualité rédactionnelle d\'un travail universitaire ?</strong></p>',
            'Sujet divisé' => '<p>Ce travail s\'articulera en cinq grandes parties : thermodynamique, effets neurologiques, sémiologie du fromage, modèle prédictif et recommandations pratiques.</p>',
        ];

        foreach ($contenusSections as $label => $contenu) {
            $section = $sections->get($label);
            if (! $section) {
                continue;
            }
            ProjetSectionContenu::updateOrCreate(
                ['projet_id' => $projet->id, 'section_id' => $section->id],
                ['contenu' => $contenu]
            );
        }

        // ─── Paragraphes de développement ─────────────────────────────────────
        $sectionDev = $sections->get('Développement');

        if ($sectionDev) {
            ProjetSectionParagraphe::where('projet_id', $projet->id)
                ->where('section_id', $sectionDev->id)
                ->delete();

            $paragraphes = [
                [
                    'titre' => 'Thermodynamique de la pizza froide : une approche phénoménologique',
                    'contenu' => '<p>La pizza, une fois sortie du four, entame un processus irréversible de refroidissement que la physique classique nomme "dissipation thermique" et que l\'étudiant nomme "oups, j\'ai oublié de manger".</p>',
                ],
                [
                    'titre' => 'Effets neuropsychologiques de la consommation nocturne de glucides sur la syntaxe',
                    'contenu' => '<p>Plusieurs études fictives ont démontré que la consommation de glucides raffinés après minuit provoque le <strong>syndrome de la phrase sans fin</strong>.</p>',
                ],
                [
                    'titre' => 'Le fromage comme métaphore de la procrastination : analyse sémiologique',
                    'contenu' => '<p>Tout comme le fromage, la procrastination commence chaude et prometteuse, puis se solidifie en une masse compacte et difficile à décoller de la conscience de l\'étudiant.</p>',
                ],
                [
                    'titre' => 'Modèle prédictif : corrélation entre pointes de pizza et note finale',
                    'contenu' => '<p><strong>Note finale = (Nombre de pointes × 1,7) − (Heures de sommeil × 0,3) + (Tasses de café × 2,1) − Constante de désespoir</strong></p>',
                ],
                [
                    'titre' => 'Recommandations pratiques à l\'intention des futurs étudiants en état de crise',
                    'contenu' => '<ul><li>Commander la pizza avant 21h.</li><li>Garder au moins une tranche pour le matin.</li><li>Ne pas lire ses propres notes à jeun.</li></ul>',
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
        }

        $this->command->info("Projet \"{$projet->titre_projet}\" rempli.");

        // ─── Conclusions par membre (section type 'individuel') ───────────────
        $sectionConclusion = $sections->get('Conclusion');

        if ($sectionConclusion) {
            foreach ($groupe->membres as $membre) {
                ProjetConclusion::updateOrCreate(
                    ['projet_id' => $projet->id, 'user_id' => $membre->id, 'section_id' => $sectionConclusion->id],
                    [
                        'contenu' => "<p>Au terme de cette exploration, {$membre->prenom} {$membre->nom} conclut que la pizza froide constitue un pilier fondamental de l'expérience universitaire moderne.</p><p>Chaque pointe consommée dans l'obscurité est un témoignage silencieux de l'effort académique, même quand cet effort ressemble à de la survie.</p>",
                    ],
                );

                $this->command->info("Conclusion créée pour {$membre->prenom} {$membre->nom}.");
            }
        }
    }
}
