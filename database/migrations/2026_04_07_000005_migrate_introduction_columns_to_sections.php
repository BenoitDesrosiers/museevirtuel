<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pour chaque TypeProjet sans sections :
     *  1. Insère les 5 sections standards (sujet amené/posé/divisé, développement, conclusion).
     *  2. Migre introduction_amener/poser/diviser → projet_section_contenus.
     *  3. Supprime les 3 colonnes dépréciées de projets_recherche.
     */
    public function up(): void
    {
        $now = now();

        $sectionsData = [
            ['label' => 'Sujet amené',   'type' => 'texte',       'ordre' => 1],
            ['label' => 'Sujet posé',    'type' => 'texte',       'ordre' => 2],
            ['label' => 'Sujet divisé',  'type' => 'texte',       'ordre' => 3],
            ['label' => 'Développement', 'type' => 'paragraphes', 'ordre' => 4],
            ['label' => 'Conclusion',    'type' => 'individuel',  'ordre' => 5],
        ];

        // TypeProjets qui n'ont encore aucune section
        $typeProjetIds = DB::table('types_projets')
            ->whereNotIn('id', fn ($q) => $q->select('type_projet_id')->from('type_projet_sections'))
            ->pluck('id');

        foreach ($typeProjetIds as $typeProjetId) {
            // 1. Insérer les 5 sections
            $sectionIds = [];
            foreach ($sectionsData as $section) {
                $sectionIds[$section['ordre']] = DB::table('type_projet_sections')->insertGetId([
                    'type_projet_id' => $typeProjetId,
                    'label' => $section['label'],
                    'type' => $section['type'],
                    'ordre' => $section['ordre'],
                    'description' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            // 2. Migrer les données d'introduction vers projet_section_contenus
            $projets = DB::table('projets_recherche')
                ->where('type_projet_id', $typeProjetId)
                ->get(['id', 'introduction_amener', 'introduction_poser', 'introduction_diviser']);

            foreach ($projets as $projet) {
                $map = [
                    1 => $projet->introduction_amener,
                    2 => $projet->introduction_poser,
                    3 => $projet->introduction_diviser,
                ];
                foreach ($map as $ordre => $contenu) {
                    if ($contenu === null) {
                        continue;
                    }
                    DB::table('projet_section_contenus')->updateOrInsert(
                        ['projet_id' => $projet->id, 'section_id' => $sectionIds[$ordre]],
                        ['contenu' => $contenu, 'created_at' => $now, 'updated_at' => $now]
                    );
                }
            }
        }

        // 3. Supprimer les colonnes dépréciées
        Schema::table('projets_recherche', function (Blueprint $table) {
            $table->dropColumn(['introduction_amener', 'introduction_poser', 'introduction_diviser']);
        });
    }

    public function down(): void
    {
        Schema::table('projets_recherche', function (Blueprint $table) {
            $table->text('introduction_amener')->nullable()->after('titre_projet');
            $table->text('introduction_poser')->nullable()->after('introduction_amener');
            $table->text('introduction_diviser')->nullable()->after('introduction_poser');
        });
    }
};
