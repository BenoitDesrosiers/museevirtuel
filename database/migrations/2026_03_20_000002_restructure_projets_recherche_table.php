<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Restructure projets_recherche : un projet par groupe (suppression user_id et conclusion).
     * Déduplique d'abord les lignes existantes (ancienne archi : N projets par groupe).
     */
    public function up(): void
    {
        // Dédupliquer : conserver uniquement la ligne avec le plus petit id par groupe
        // Alias intermédiaire nécessaire pour MySQL (impossible de DELETE et SELECT la même table directement)
        DB::statement('
            DELETE FROM projets_recherche
            WHERE id NOT IN (
                SELECT min_id FROM (
                    SELECT MIN(id) AS min_id
                    FROM projets_recherche
                    GROUP BY groupe_id
                ) AS tmp
            )
        ');

        Schema::table('projets_recherche', function (Blueprint $table) {
            // Supprimer l'ancien index composite uniquement s'il existe (migration idempotente)
            if (Schema::hasColumn('projets_recherche', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            }

            if (Schema::hasColumn('projets_recherche', 'conclusion')) {
                $table->dropColumn('conclusion');
            }

            // Ajouter le unique sur groupe_id uniquement s'il n'existe pas déjà
            // (la migration originale le crée déjà — on ne fait rien ici)
        });
    }

    /**
     * Restaure la structure originale.
     */
    public function down(): void
    {
        Schema::table('projets_recherche', function (Blueprint $table) {
            $table->dropUnique(['groupe_id']);

            $table->foreignId('user_id')
                ->after('groupe_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnDelete();

            $table->text('conclusion')->nullable()->after('dev_5_contenu');

            $table->unique(['groupe_id', 'user_id']);
        });
    }
};
