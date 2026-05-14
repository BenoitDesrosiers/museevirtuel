<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajoute une contrainte unique sur (nom, etablissement_id) pour empêcher
     * la création de thématiques en doublon au sein d'un même établissement.
     *
     * Les doublons existants sont d'abord supprimés en conservant la ligne
     * la plus ancienne (id le plus bas) pour chaque paire (nom, etablissement_id).
     */
    public function up(): void
    {
        // Supprimer les doublons existants avant d'appliquer la contrainte.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('
                DELETE t1 FROM thematiques t1
                INNER JOIN thematiques t2
                    ON t1.nom = t2.nom
                    AND t1.etablissement_id = t2.etablissement_id
                    AND t1.id > t2.id
            ');
        } else {
            // SQLite : sous-requête corrélée
            DB::statement('
                DELETE FROM thematiques
                WHERE id NOT IN (
                    SELECT MIN(id)
                    FROM thematiques
                    WHERE etablissement_id IS NOT NULL
                    GROUP BY nom, etablissement_id
                )
                AND etablissement_id IS NOT NULL
            ');
        }

        Schema::table('thematiques', function (Blueprint $table) {
            $table->unique(['etablissement_id', 'nom'], 'thematiques_etablissement_nom_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('thematiques', function (Blueprint $table) {
            $table->dropUnique('thematiques_etablissement_nom_unique');
        });
    }
};
