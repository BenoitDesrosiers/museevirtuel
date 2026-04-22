<?php

namespace Database\Seeders;

use App\Models\Etablissement;
use App\Models\Thematique;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ThematiqueDemoSeeder extends Seeder
{
    /**
     * Vide la table thematiques et insère 8 thématiques par établissement existant.
     * Les thématiques appartiennent à l'établissement, pas à un enseignant individuel.
     */
    public function run(): void
    {
        // Vider les tables en respectant les FK selon le driver
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            DB::table('groupe_thematique')->truncate();
            DB::table('thematiques')->truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        } else {
            DB::statement('PRAGMA foreign_keys = OFF');
            DB::table('groupe_thematique')->delete();
            DB::table('thematiques')->delete();
            DB::statement('PRAGMA foreign_keys = ON');
        }

        $this->command->info('Tables groupe_thematique et thematiques vidées.');

        $etablissements = Etablissement::all();

        if ($etablissements->isEmpty()) {
            $this->command->warn('Aucun établissement trouvé dans la base de données.');

            return;
        }

        $themes = [
            [
                'nom' => 'La Nouvelle-France',
                'description' => 'Exploration de la période coloniale française en Amérique du Nord, de la fondation de Québec à la Conquête.',
                'periode_historique' => '1534 – 1763',
            ],
            [
                'nom' => 'La Révolution industrielle au Québec',
                'description' => 'Transformation économique et sociale du Québec lors de l\'industrialisation, avec l\'essor des manufactures et de l\'urbanisation.',
                'periode_historique' => '1850 – 1920',
            ],
            [
                'nom' => 'Les Premières Nations du Québec',
                'description' => 'Cultures, traditions et histoire des peuples autochtones du territoire québécois, avant et après la colonisation.',
                'periode_historique' => 'Préhistoire – présent',
            ],
            [
                'nom' => 'La Révolution tranquille',
                'description' => 'Période de modernisation profonde du Québec : laïcisation, nationalisme, essor de l\'État québécois et transformations culturelles.',
                'periode_historique' => '1960 – 1980',
            ],
            [
                'nom' => 'La Seconde Guerre mondiale et le Québec',
                'description' => 'Participation du Canada et du Québec au conflit mondial, crise de la conscription, effort de guerre et répercussions sociales.',
                'periode_historique' => '1939 – 1945',
            ],
            [
                'nom' => 'L\'art et la culture au Québec',
                'description' => 'Évolution des expressions artistiques québécoises : littérature, peinture, musique et cinéma depuis le XIXe siècle.',
                'periode_historique' => 'XIXe siècle – présent',
            ],
            [
                'nom' => 'L\'immigration et la diversité culturelle',
                'description' => 'Vagues d\'immigration au Québec, intégration des communautés culturelles et construction d\'une identité plurielle.',
                'periode_historique' => 'XIXe siècle – présent',
            ],
            [
                'nom' => 'Le mouvement patriote de 1837-1838',
                'description' => 'Insurrections des Patriotes contre le gouvernement colonial britannique, leurs causes, leur déroulement et leurs conséquences.',
                'periode_historique' => '1837 – 1838',
            ],
        ];

        foreach ($etablissements as $etablissement) {
            $count = 0;

            foreach ($themes as $theme) {
                Thematique::create(array_merge($theme, [
                    'etablissement_id' => $etablissement->id,
                ]));
                $count++;
            }

            $this->command->info(
                "✓ {$count} thématiques créées pour {$etablissement->nom} (id={$etablissement->id})"
            );
        }

        $total = Thematique::count();
        $this->command->info("Total : {$total} thématiques insérées.");
    }
}
