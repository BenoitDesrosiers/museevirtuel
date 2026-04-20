<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sprint 13 — Ajout du niveau intermédiaire Classe (section de cours) :
     *   Ancien `Classe` (table: classes) → `Groupe` (table: groupes)
     *   Nouveau modèle `Classe` (table: classes) → section d'un Cours
     *
     * Hiérarchie cible : Cours → Classe → Groupe → Projet
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // ── Étape 1 : Libérer le nom « classe_etudiant » ──────────────────────
        // (d'abord renommer l'actuel pour éviter un conflit de nom)
        Schema::rename('classe_etudiant', 'groupe_etudiant');
        Schema::rename('cours_etudiant', 'classe_etudiant');

        // ── Étape 2 : Renommer toutes les tables classe_* → groupe_* ──────────
        Schema::rename('classe_thematique', 'groupe_thematique');
        Schema::rename('classe_notes', 'groupe_notes');
        Schema::rename('classe_medias', 'groupe_medias');
        Schema::rename('classe_note_corrections', 'groupe_note_corrections');
        Schema::rename('classe_echanges', 'groupe_echanges');

        // ── Étape 3 : Renommer la table principale classes → groupes ──────────
        Schema::rename('classes', 'groupes');

        // ── Étape 4 : Créer la nouvelle table classes (sections de cours) ─────
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cours_id')->constrained('cours')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('nom', 100)->nullable();
            $table->timestamps();
        });

        // ── Étape 5 : Migration de données ────────────────────────────────────
        // Créer une Classe par défaut pour chaque Cours existant
        // Le code de la Classe reprend l'ancien identifiant `groupe` du Cours
        DB::statement("
            INSERT INTO classes (cours_id, code, created_at, updated_at)
            SELECT id, COALESCE(NULLIF(groupe, ''), CONCAT('CL-', LPAD(id, 3, '0'))), NOW(), NOW()
            FROM cours
        ");

        // ── Étape 6 : Renommer cours_id → classe_id dans groupes ──────────────
        Schema::table('groupes', function (Blueprint $table) {
            $table->renameColumn('cours_id', 'classe_id');
        });

        // ── Étape 7 : Mettre à jour groupes.classe_id ─────────────────────────
        // Avant : classe_id = ancien cours_id
        // Après : classe_id = ID de la Classe par défaut créée pour ce cours
        DB::statement('
            UPDATE groupes g
            INNER JOIN classes c ON c.cours_id = g.classe_id
            SET g.classe_id = c.id
        ');

        // ── Étape 8 : Renommer FK dans groupe_etudiant ────────────────────────
        Schema::table('groupe_etudiant', function (Blueprint $table) {
            $table->renameColumn('classe_id', 'groupe_id');
        });

        // ── Étape 9 : Renommer FK dans toutes les tables groupe_* ─────────────
        Schema::table('groupe_thematique', function (Blueprint $table) {
            $table->renameColumn('classe_id', 'groupe_id');
        });

        Schema::table('groupe_notes', function (Blueprint $table) {
            $table->renameColumn('classe_id', 'groupe_id');
        });

        Schema::table('groupe_medias', function (Blueprint $table) {
            $table->renameColumn('classe_id', 'groupe_id');
        });

        Schema::table('groupe_echanges', function (Blueprint $table) {
            $table->renameColumn('classe_id', 'groupe_id');
        });

        // ── Étape 10 : Renommer FK dans projets_recherche ─────────────────────
        Schema::table('projets_recherche', function (Blueprint $table) {
            $table->renameColumn('classe_id', 'groupe_id');
        });

        // ── Étape 11 : Renommer cours_id → classe_id dans classe_etudiant ─────
        Schema::table('classe_etudiant', function (Blueprint $table) {
            $table->renameColumn('cours_id', 'classe_id');
        });

        // ── Étape 12 : Mettre à jour classe_etudiant.classe_id ────────────────
        // Avant : classe_id = ancien cours_id
        // Après : classe_id = ID de la Classe par défaut
        DB::statement('
            UPDATE classe_etudiant ce
            INNER JOIN classes c ON c.cours_id = ce.classe_id
            SET ce.classe_id = c.id
        ');

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // Restaurer classe_etudiant.classe_id → cours_id
        Schema::table('classe_etudiant', function (Blueprint $table) {
            $table->renameColumn('classe_id', 'cours_id');
        });

        // Restaurer projets_recherche.groupe_id → classe_id
        Schema::table('projets_recherche', function (Blueprint $table) {
            $table->renameColumn('groupe_id', 'classe_id');
        });

        // Restaurer FK groupe_* → classe_id
        Schema::table('groupe_echanges', function (Blueprint $table) {
            $table->renameColumn('groupe_id', 'classe_id');
        });
        Schema::table('groupe_medias', function (Blueprint $table) {
            $table->renameColumn('groupe_id', 'classe_id');
        });
        Schema::table('groupe_notes', function (Blueprint $table) {
            $table->renameColumn('groupe_id', 'classe_id');
        });
        Schema::table('groupe_thematique', function (Blueprint $table) {
            $table->renameColumn('groupe_id', 'classe_id');
        });
        Schema::table('groupe_etudiant', function (Blueprint $table) {
            $table->renameColumn('groupe_id', 'classe_id');
        });

        // Restaurer groupes.classe_id → cours_id
        Schema::table('groupes', function (Blueprint $table) {
            $table->renameColumn('classe_id', 'cours_id');
        });

        // Supprimer la nouvelle table classes
        Schema::dropIfExists('classes');

        // Renommer groupes → classes
        Schema::rename('groupes', 'classes');

        // Renommer les tables groupe_* → classe_*
        Schema::rename('groupe_echanges', 'classe_echanges');
        Schema::rename('groupe_note_corrections', 'classe_note_corrections');
        Schema::rename('groupe_medias', 'classe_medias');
        Schema::rename('groupe_notes', 'classe_notes');
        Schema::rename('groupe_thematique', 'classe_thematique');

        // Restaurer les pivots
        Schema::rename('classe_etudiant', 'cours_etudiant');
        Schema::rename('groupe_etudiant', 'classe_etudiant');

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
