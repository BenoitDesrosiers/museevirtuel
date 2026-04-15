<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sprint 12 — Refactoring hiérarchique :
     *   Classe (table: classes) → Cours (table: cours)
     *   Groupe (table: groupes) → Classe (table: classes)
     *
     * Tables renommées, FK mises à jour, colonne `code` ajoutée aux classes.
     */
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // ── Étape 1 : Renommer les tables ─────────────────────────────────────
        Schema::rename('classes', 'cours');
        Schema::rename('groupes', 'classes');
        Schema::rename('classe_etudiant', 'cours_etudiant');
        Schema::rename('groupe_etudiant', 'classe_etudiant');
        Schema::rename('groupe_thematique', 'classe_thematique');
        Schema::rename('groupe_notes', 'classe_notes');
        Schema::rename('groupe_medias', 'classe_medias');
        Schema::rename('groupe_note_corrections', 'classe_note_corrections');
        Schema::rename('classe_documents', 'cours_documents');
        Schema::rename('groupe_echanges', 'classe_echanges');

        // ── Étape 2 : FK classe_id → cours_id ────────────────────────────────
        // (tables qui référençaient l'ancienne table 'classes')
        Schema::table('classes', function (Blueprint $table) {
            $table->renameColumn('classe_id', 'cours_id');
        });
        Schema::table('cours_etudiant', function (Blueprint $table) {
            $table->renameColumn('classe_id', 'cours_id');
        });
        Schema::table('cours_documents', function (Blueprint $table) {
            $table->renameColumn('classe_id', 'cours_id');
        });
        Schema::table('echeancier_etapes', function (Blueprint $table) {
            $table->renameColumn('classe_id', 'cours_id');
        });

        // ── Étape 3 : FK groupe_id → classe_id ───────────────────────────────
        // (tables qui référençaient l'ancienne table 'groupes')
        Schema::table('classe_etudiant', function (Blueprint $table) {
            $table->renameColumn('groupe_id', 'classe_id');
        });
        Schema::table('classe_thematique', function (Blueprint $table) {
            $table->renameColumn('groupe_id', 'classe_id');
        });
        Schema::table('classe_notes', function (Blueprint $table) {
            $table->renameColumn('groupe_id', 'classe_id');
        });
        Schema::table('classe_medias', function (Blueprint $table) {
            $table->renameColumn('groupe_id', 'classe_id');
        });
        Schema::table('classe_echanges', function (Blueprint $table) {
            $table->renameColumn('groupe_id', 'classe_id');
        });
        Schema::table('projets_recherche', function (Blueprint $table) {
            $table->renameColumn('groupe_id', 'classe_id');
        });

        // ── Étape 4 : Colonne `code` sur la nouvelle table `classes` ─────────
        Schema::table('classes', function (Blueprint $table) {
            $table->string('code', 20)->nullable()->after('cours_id');
        });

        // Peupler le code depuis l'ID existant (ex: "CL-0001")
        DB::statement("UPDATE classes SET code = CONCAT('CL-', LPAD(id, 4, '0'))");

        Schema::table('classes', function (Blueprint $table) {
            $table->string('code', 20)->nullable(false)->change();
        });

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // Retirer la colonne code
        Schema::table('classes', function (Blueprint $table) {
            $table->dropColumn('code');
        });

        // Restaurer FK groupe_id
        Schema::table('projets_recherche', function (Blueprint $table) {
            $table->renameColumn('classe_id', 'groupe_id');
        });
        Schema::table('classe_echanges', function (Blueprint $table) {
            $table->renameColumn('classe_id', 'groupe_id');
        });
        Schema::table('classe_medias', function (Blueprint $table) {
            $table->renameColumn('classe_id', 'groupe_id');
        });
        Schema::table('classe_notes', function (Blueprint $table) {
            $table->renameColumn('classe_id', 'groupe_id');
        });
        Schema::table('classe_thematique', function (Blueprint $table) {
            $table->renameColumn('classe_id', 'groupe_id');
        });
        Schema::table('classe_etudiant', function (Blueprint $table) {
            $table->renameColumn('classe_id', 'groupe_id');
        });

        // Restaurer FK cours_id → classe_id
        Schema::table('echeancier_etapes', function (Blueprint $table) {
            $table->renameColumn('cours_id', 'classe_id');
        });
        Schema::table('cours_documents', function (Blueprint $table) {
            $table->renameColumn('cours_id', 'classe_id');
        });
        Schema::table('cours_etudiant', function (Blueprint $table) {
            $table->renameColumn('cours_id', 'classe_id');
        });
        Schema::table('classes', function (Blueprint $table) {
            $table->renameColumn('cours_id', 'classe_id');
        });

        // Restaurer les tables (ordre inverse)
        Schema::rename('classe_echanges', 'groupe_echanges');
        Schema::rename('cours_documents', 'classe_documents');
        Schema::rename('classe_note_corrections', 'groupe_note_corrections');
        Schema::rename('classe_medias', 'groupe_medias');
        Schema::rename('classe_notes', 'groupe_notes');
        Schema::rename('classe_thematique', 'groupe_thematique');
        Schema::rename('classe_etudiant', 'groupe_etudiant');
        Schema::rename('cours_etudiant', 'classe_etudiant');
        Schema::rename('classes', 'groupes');
        Schema::rename('cours', 'classes');

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
