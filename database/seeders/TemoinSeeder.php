<?php

namespace Database\Seeders;

use App\Models\Thematique;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TemoinSeeder extends Seeder
{
    /**
     * Crée 10 témoins (personnes âgées) de démo avec des thématiques variées.
     *
     * temoin1 est compatible avec le groupe créé par DemoSeeder (thématique :
     * "La Révolution tranquille").
     */
    public function run(): void
    {
        // Enseignant démo — nécessaire pour créer les thématiques manquantes
        $prof = User::where('email', 'prof@demo.com')->first();

        $thematiquesDef = [
            'La Révolution tranquille' => ['periode_historique' => '1960 – 1980',     'description' => 'Modernisation profonde du Québec : laïcisation, nationalisme et essor de l\'État québécois.'],
            'La Nouvelle-France' => ['periode_historique' => '1534 – 1763',     'description' => 'Colonisation française en Amérique du Nord, de la fondation à la Conquête britannique.'],
            'Les Premières Nations du Québec' => ['periode_historique' => 'Préhistoire – aujourd\'hui', 'description' => 'Histoire, culture et luttes des nations autochtones du territoire québécois.'],
            'La Révolution industrielle au Québec' => ['periode_historique' => '1850 – 1950',  'description' => 'Industrialisation, urbanisation et transformation du monde ouvrier québécois.'],
            'La Seconde Guerre mondiale' => ['periode_historique' => '1939 – 1945',     'description' => 'Participation du Québec et du Canada au conflit mondial et impacts sur la société.'],
            "L'art et la culture au Québec" => ['periode_historique' => 'XXᵉ siècle',         'description' => 'Évolution des arts, de la littérature et de la culture populaire québécoise.'],
            "L'immigration et la diversité culturelle" => ['periode_historique' => '1960 – aujourd\'hui', 'description' => 'Vagues migratoires, intégration et construction d\'une société pluriculturelle.'],
            'Le mouvement patriote de 1837-1838' => ['periode_historique' => '1837 – 1838',    'description' => 'Rébellions des Patriotes contre le pouvoir colonial britannique au Bas-Canada.'],
        ];

        // Créer les thématiques manquantes si un enseignant démo existe
        if ($prof) {
            foreach ($thematiquesDef as $nom => $attrs) {
                Thematique::firstOrCreate(
                    ['nom' => $nom, 'enseignant_id' => $prof->id],
                    array_merge($attrs, ['enseignant_id' => $prof->id]),
                );
            }
        }

        // Index des thématiques par nom pour une résolution rapide
        $thematiques = Thematique::pluck('id', 'nom');

        $temoins = [
            [
                'prenom' => 'Hélène',
                'nom' => 'Tremblay',
                'email' => 'temoin1@demo.com',
                'provenance' => 'Montréal',
                'description' => 'Née à Montréal en 1952, j\'ai vécu la Révolution tranquille de l\'intérieur. J\'ai enseigné au secondaire pendant 30 ans et je souhaite partager mon témoignage.',
                'theme_noms' => ['La Révolution tranquille'],
            ],
            [
                'prenom' => 'Gérard',
                'nom' => 'Beauchamp',
                'email' => 'temoin2@demo.com',
                'provenance' => 'Québec',
                'description' => 'Archiviste retraité, passionné par la Nouvelle-France et les origines de la colonisation française en Amérique du Nord.',
                'theme_noms' => ['La Nouvelle-France'],
            ],
            [
                'prenom' => 'Pauline',
                'nom' => 'Gagnon',
                'email' => 'temoin3@demo.com',
                'provenance' => 'Sept-Îles',
                'description' => 'Militante pour les droits des Premières Nations depuis 40 ans. Je veux partager mon histoire et celle de ma communauté innue.',
                'theme_noms' => ['Les Premières Nations du Québec'],
            ],
            [
                'prenom' => 'Roger',
                'nom' => 'Lapointe',
                'email' => 'temoin4@demo.com',
                'provenance' => 'Sherbrooke',
                'description' => 'Fils d\'ouvrier d\'usine, j\'ai grandi au cœur de l\'industrialisation québécoise des années 1950-1960 dans le quartier ouvrier de Sherbrooke.',
                'theme_noms' => ['La Révolution industrielle au Québec'],
            ],
            [
                'prenom' => 'Simone',
                'nom' => 'Michaud',
                'email' => 'temoin5@demo.com',
                'provenance' => 'Montréal',
                'description' => 'Ma famille a traversé la Seconde Guerre mondiale. Mon père était vétéran et ma mère travaillait dans une usine de munitions à Montréal.',
                'theme_noms' => ['La Seconde Guerre mondiale'],
            ],
            [
                'prenom' => 'Fernand',
                'nom' => 'Côté',
                'email' => 'temoin6@demo.com',
                'provenance' => 'Trois-Rivières',
                'description' => 'Peintre et musicien amateur, j\'ai consacré ma vie à la préservation de la culture québécoise. J\'ai participé à plusieurs mouvements artistiques des années 1960-1970.',
                'theme_noms' => ['L\'art et la culture au Québec'],
            ],
            [
                'prenom' => 'Maria',
                'nom' => 'Santos',
                'email' => 'temoin7@demo.com',
                'provenance' => 'Laval',
                'description' => 'Arrivée au Québec en 1975, j\'ai vécu l\'intégration au sein de ma communauté portugaise tout en m\'intégrant au tissu francophone de Laval.',
                'theme_noms' => ['L\'immigration et la diversité culturelle'],
            ],
            [
                'prenom' => 'Yves',
                'nom' => 'Leblanc',
                'email' => 'temoin8@demo.com',
                'provenance' => 'Saint-Denis-sur-Richelieu',
                'description' => 'Historien autodidacte passionné par les rébellions de 1837-1838. Mes ancêtres étaient Patriotes et j\'ai retracé leurs archives toute ma vie.',
                'theme_noms' => ['Le mouvement patriote de 1837-1838'],
            ],
            [
                'prenom' => 'Louisette',
                'nom' => 'Bergeron',
                'email' => 'temoin9@demo.com',
                'provenance' => 'Chicoutimi',
                'description' => 'Professeure retraitée ayant enseigné l\'histoire du Québec pendant 35 ans. Spécialisée en Révolution tranquille et cultures autochtones.',
                'theme_noms' => ['La Révolution tranquille', 'Les Premières Nations du Québec'],
            ],
            [
                'prenom' => 'Armand',
                'nom' => 'Bouchard',
                'email' => 'temoin10@demo.com',
                'provenance' => 'Joliette',
                'description' => 'Généalogiste passionné, j\'ai retracé les racines de ma famille depuis la Nouvelle-France jusqu\'aux débuts de l\'industrialisation en Lanaudière.',
                'theme_noms' => ['La Nouvelle-France', 'La Révolution industrielle au Québec'],
            ],
        ];

        foreach ($temoins as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'prenom' => $data['prenom'],
                    'nom' => $data['nom'],
                    'password' => Hash::make('password'),
                    'role' => 'personne_agee',
                    'statut' => 'en_attente',
                    'email_verified_at' => now(),
                    'description' => $data['description'],
                    'provenance' => $data['provenance'],
                ]
            );

            $ids = collect($data['theme_noms'])
                ->map(fn ($nom) => $thematiques->get($nom))
                ->filter()
                ->values()
                ->toArray();

            if (! empty($ids)) {
                $user->thematiquesChoisies()->sync($ids);
            }
        }
    }
}
