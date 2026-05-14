<?php

namespace Database\Seeders;

use App\Models\Thematique;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'prenom' => 'Admin',
                'nom' => 'Système',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );

        $this->call(GabaritCoursCompletSeeder::class);
        $this->call(DemoSeeder::class);
        $this->call(DepDemoSeeder::class);
        $this->call(CoursComplementaireDemoSeeder::class);
        $this->call(EcheancierEtapesSeeder::class);
        $this->call(GrilleCorrectionSeeder::class);
        $this->call(EtablissementSeeder::class);
        $this->call(TemoinSeeder::class);

        $enseignant = User::where('role', 'enseignant')->first();

        if ($enseignant) {
            $thematiques = [
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
                    'nom' => 'La Seconde Guerre mondiale',
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

            // EtablissementSeeder a déjà tourné : $enseignant->etablissement_id est défini.
            foreach ($thematiques as $data) {
                Thematique::firstOrCreate(
                    ['nom' => $data['nom'], 'enseignant_id' => $enseignant->id],
                    array_merge($data, [
                        'enseignant_id' => $enseignant->id,
                        'etablissement_id' => $enseignant->etablissement_id,
                    ])
                );
            }
        }
    }
}
