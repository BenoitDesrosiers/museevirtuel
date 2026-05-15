<?php

namespace Database\Seeders;

use App\Models\GabaritCours;
use App\Models\GabaritCoursObjectif;
use Illuminate\Database\Seeder;

/**
 * Peuple le gabarit "DEP" avec les objectifs pédagogiques.
 *
 * Ce seeder est idempotent : relancer ne crée pas de doublons.
 * Source : consignes du projet "Votre histoire, notre histoire" — DEP.
 */
class GabaritDepSeeder extends Seeder
{
    /**
     * Objectifs pédagogiques du cours DEP.
     *
     * @var list<string>
     */
    private const OBJECTIFS = [
        'Effectuer une étude de cas en lien avec l\'histoire du Québec par l\'entremise d\'une entrevue semi-dirigée.',
        'Appliquer la méthode historique sur une réalité humaine.',
        'Lier les différents concepts abordés dans le cours par l\'analyse d\'un récit de vie en lien avec un ou des événements ayant construit le Québec d\'aujourd\'hui.',
        'Effectuer des liens entre les témoignages des personnes aînées et les problématiques contemporaines de votre domaine professionnel.',
        'Développer ses qualités humaines en interagissant avec une personne aînée, témoin du passé.',
        'Contribuer au développement et à la pérennité des connaissances et du savoir de l\'héritage historique du patrimoine québécois.',
    ];

    /**
     * Peuple le gabarit DEP.
     *
     * Idempotent : si le gabarit existe déjà, les objectifs ne sont
     * pas recréés tant que la table n'est pas vide.
     */
    public function run(): void
    {
        /** @var GabaritCours $gabarit */
        $gabarit = GabaritCours::firstOrCreate(
            ['slug' => 'dep'],
            [
                'type_cours' => 'dep',
                'nom' => 'Gabarit — DEP',
            ]
        );

        $this->seederObjectifs($gabarit);
    }

    /**
     * Recrée les objectifs pédagogiques (supprime et réinsère pour rester à jour).
     */
    private function seederObjectifs(GabaritCours $gabarit): void
    {
        $gabarit->objectifs()->delete();

        foreach (self::OBJECTIFS as $ordre => $contenu) {
            GabaritCoursObjectif::create([
                'gabarit_cours_id' => $gabarit->id,
                'contenu' => $contenu,
                'ordre' => $ordre + 1,
            ]);
        }
    }
}
