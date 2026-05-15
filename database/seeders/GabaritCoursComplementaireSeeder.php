<?php

namespace Database\Seeders;

use App\Models\GabaritCours;
use App\Models\GabaritCoursObjectif;
use App\Models\GabaritCoursReference;
use Illuminate\Database\Seeder;

/**
 * Peuple le gabarit "cours complémentaire" avec les objectifs pédagogiques
 * et les références bibliographiques.
 *
 * Ce seeder est idempotent : relancer ne crée pas de doublons.
 * Source : consignes du projet "Votre histoire, notre histoire" — cours complémentaire.
 */
class GabaritCoursComplementaireSeeder extends Seeder
{
    /**
     * Objectifs pédagogiques du cours complémentaire.
     *
     * @var list<string>
     */
    private const OBJECTIFS = [
        'Mieux comprendre un événement marquant de l\'histoire du Québec.',
        'Découvrir comment on peut étudier le passé à partir de témoignages.',
        'Établir des liens entre l\'histoire collective et l\'expérience personnelle.',
        'Développer une réflexion critique et humaine sur la mémoire du passé.',
        'Reconnaître l\'importance de la transmission intergénérationnelle.',
    ];

    /**
     * Revues historiques recommandées pour la recherche documentaire.
     * Source : consignes du projet "Votre histoire, notre histoire" — cours complémentaire.
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
     * Peuple le gabarit cours complémentaire.
     *
     * Idempotent : si le gabarit existe déjà, les sous-éléments ne sont
     * pas recréés tant que la table n'est pas vide.
     */
    public function run(): void
    {
        /** @var GabaritCours $gabarit */
        $gabarit = GabaritCours::firstOrCreate(
            ['slug' => 'cours_complementaire'],
            [
                'type_cours' => 'cours_complementaire',
                'nom' => 'Gabarit — Cours complémentaire',
            ]
        );

        $this->seederObjectifs($gabarit);
        $this->seederReferences($gabarit);
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

    /**
     * Recrée les références bibliographiques (supprime et réinsère pour rester à jour).
     */
    private function seederReferences(GabaritCours $gabarit): void
    {
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
