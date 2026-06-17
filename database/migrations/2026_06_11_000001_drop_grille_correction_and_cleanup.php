<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Supprime toutes les tables liées à la grille de correction et les colonnes malus
 * suite au refactoring de la branche Changement-grille-de-correction.
 *
 * Tables supprimées : projet_notes, projet_grille_malus, projet_grille_notes,
 *                     grille_malus, grille_criteres, grilles_correction
 *
 * Colonnes supprimées de projet_annotations : points_malus, type, cible_user_id
 */
return new class extends Migration
{
    public function up(): void
    {
        // Supprimer les tables de données d'abord (dépendances FK)
        Schema::dropIfExists('projet_grille_malus');
        Schema::dropIfExists('projet_grille_notes');
        Schema::dropIfExists('projet_notes');

        // Supprimer les tables de définition
        Schema::dropIfExists('grille_malus');
        Schema::dropIfExists('grille_criteres');
        Schema::dropIfExists('grilles_correction');

        // Supprimer les colonnes malus des annotations inline (si elles existent).
        // Sur une DB fraîche (tests), ces colonnes n'existent pas — les anciennes migrations étant supprimées.
        $this->supprimerColonnesAnnotations();
    }

    public function down(): void
    {
        // Cette migration est intentionnellement sans rollback complet.
        // La reconstruction des tables grille se fait via le nouvel historique de migrations.

        // Remettre les colonnes supprimées de projet_annotations
        Schema::table('projet_annotations', function (Blueprint $table) {
            $table->decimal('points_malus', 5, 2)->unsigned()->nullable()->after('mot_annote');
            $table->string('type')->default('commentaire')->after('contenu');
            $table->foreignId('cible_user_id')->nullable()->constrained('users')->nullOnDelete()->after('user_id');
        });
    }

    /**
     * Supprime les colonnes malus de projet_annotations selon le pilote de base de données.
     *
     * SQLite interdit ALTER TABLE DROP COLUMN sur une colonne qui est source d'une FK
     * (ex. cible_user_id → users). La seule solution portable est de reconstruire la table
     * sans cette colonne via un CREATE/INSERT/DROP/RENAME explicite.
     * MySQL supprime implicitement la FK lors du DROP COLUMN — pas de cas particulier nécessaire.
     */
    private function supprimerColonnesAnnotations(): void
    {
        $colonnesACibler = ['points_malus', 'type', 'cible_user_id'];
        $colonnesPresentes = array_filter(
            $colonnesACibler,
            fn (string $col) => Schema::hasColumn('projet_annotations', $col)
        );

        if (empty($colonnesPresentes)) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            // Reconstruction complète de la table : approche standard pour SQLite quand
            // ALTER TABLE DROP COLUMN est impossible (colonne FK source).
            DB::statement('PRAGMA foreign_keys = OFF');

            DB::unprepared('CREATE TABLE "projet_annotations_rebuilt" (
                "id" integer primary key autoincrement not null,
                "projet_id" integer not null,
                "champ" varchar not null,
                "commentaire_id" varchar not null,
                "contenu" text not null,
                "user_id" integer not null,
                "created_at" datetime,
                "updated_at" datetime,
                "position" integer,
                "mot_annote" text,
                foreign key("projet_id") references "projets_recherche"("id") on delete cascade,
                foreign key("user_id") references "users"("id") on delete cascade
            )');

            DB::unprepared('INSERT INTO "projet_annotations_rebuilt"
                ("id","projet_id","champ","commentaire_id","contenu","user_id","created_at","updated_at","position","mot_annote")
                SELECT "id","projet_id","champ","commentaire_id","contenu","user_id","created_at","updated_at","position","mot_annote"
                FROM "projet_annotations"');

            DB::unprepared('DROP TABLE "projet_annotations"');
            DB::unprepared('ALTER TABLE "projet_annotations_rebuilt" RENAME TO "projet_annotations"');

            DB::statement('PRAGMA foreign_keys = ON');
        } else {
            Schema::table('projet_annotations', function (Blueprint $table) use ($colonnesPresentes) {
                $table->dropColumn(array_values($colonnesPresentes));
            });
        }
    }
};
