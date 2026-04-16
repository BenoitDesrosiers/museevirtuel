<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Corrige les contraintes FK laissées incorrectes par la migration Sprint 13 :
     *   - classe_etudiant.classe_id   → doit référencer classes.id (pas cours.id)
     *   - groupes.classe_id           → doit référencer classes.id (pas cours.id)
     *
     * Supprime aussi la contrainte UNIQUE sur user_id seul dans classe_etudiant
     * (un étudiant peut être inscrit dans plusieurs sections de cours différents).
     *
     * Utilise des vérifications conditionnelles pour être idempotente (fonctionne
     * que la DB soit dans un état propre ou partiellement migré).
     */
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            // ── classe_etudiant ──────────────────────────────────────────────────

            // Supprimer les FK existantes (une ou deux selon l'état de la DB)
            foreach ($this->fksOf('classe_etudiant') as $name) {
                DB::statement("ALTER TABLE `classe_etudiant` DROP FOREIGN KEY `{$name}`");
            }

            // Supprimer l'index unique sur user_id seul (et remplacer par un index ordinaire)
            if ($this->hasIndex('classe_etudiant', 'classe_etudiant_user_id_unique')) {
                Schema::table('classe_etudiant', fn (Blueprint $t) => $t->dropUnique('classe_etudiant_user_id_unique'));
            }
            if (! $this->hasIndex('classe_etudiant', 'classe_etudiant_user_id_index')) {
                Schema::table('classe_etudiant', fn (Blueprint $t) => $t->index('user_id'));
            }

            // Recréer les FK avec les bonnes cibles
            Schema::table('classe_etudiant', function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('classe_id')->references('id')->on('classes')->cascadeOnDelete();
            });

            // ── groupes ──────────────────────────────────────────────────────────

            if ($this->hasFk('groupes', 'groupes_classe_id_foreign')) {
                DB::statement('ALTER TABLE `groupes` DROP FOREIGN KEY `groupes_classe_id_foreign`');
            }

            Schema::table('groupes', fn (Blueprint $t) => $t->foreign('classe_id')->references('id')->on('classes')->cascadeOnDelete()
            );
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // groupes : revenir à cours.id
        Schema::table('groupes', function (Blueprint $table) {
            $table->dropForeign('groupes_classe_id_foreign');
            $table->foreign('classe_id')->references('id')->on('cours')->cascadeOnDelete();
        });

        // classe_etudiant : revenir à cours.id + remettre le unique sur user_id
        foreach ($this->fksOf('classe_etudiant') as $name) {
            DB::statement("ALTER TABLE `classe_etudiant` DROP FOREIGN KEY `{$name}`");
        }
        if ($this->hasIndex('classe_etudiant', 'classe_etudiant_user_id_index')) {
            Schema::table('classe_etudiant', fn (Blueprint $t) => $t->dropIndex('classe_etudiant_user_id_index'));
        }

        Schema::table('classe_etudiant', function (Blueprint $table) {
            $table->unique('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('classe_id')->references('id')->on('cours')->cascadeOnDelete();
        });

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    /** Retourne les noms de toutes les FK d'une table. */
    private function fksOf(string $table): array
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->selectRaw('CONSTRAINT_NAME')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->pluck('CONSTRAINT_NAME')
            ->toArray();
    }

    /** Vérifie l'existence d'un index (par son nom) sur une table. */
    private function hasIndex(string $table, string $indexName): bool
    {
        return DB::table('information_schema.STATISTICS')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $indexName)
            ->exists();
    }

    /** Vérifie l'existence d'une FK par son nom. */
    private function hasFk(string $table, string $fkName): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $fkName)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
    }
};
