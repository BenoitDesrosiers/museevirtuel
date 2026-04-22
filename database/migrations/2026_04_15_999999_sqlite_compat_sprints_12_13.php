<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Compatibilité SQLite — équivalent des sprints 12, 13 et add_schedule.
     *
     * Sur MySQL ces transformations sont faites par les migrations MySQL-only
     * (sprint12, sprint13, fix_fk, add_schedule). Ce fichier les reproduit
     * en SQL compatible SQLite uniquement.
     *
     * Sur MySQL : no-op (early return).
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        // ── 1. classes → cours ────────────────────────────────────────────────
        Schema::rename('classes', 'cours');

        // ── 2. classe_documents → cours_documents (+ colonne classe_id → cours_id)
        Schema::rename('classe_documents', 'cours_documents');
        Schema::table('cours_documents', function (Blueprint $table) {
            $table->renameColumn('classe_id', 'cours_id');
        });

        // ── 3. echeancier_etapes : classe_id → cours_id ───────────────────────
        Schema::table('echeancier_etapes', function (Blueprint $table) {
            $table->renameColumn('classe_id', 'cours_id');
        });

        // ── 4. Ajouter colonne code à groupes ─────────────────────────────────
        //    Ajoutée par sprint 12 quand groupes s'appelait temporairement classes.
        Schema::table('groupes', function (Blueprint $table) {
            $table->string('code', 20)->nullable();
        });
        DB::table('groupes')->orderBy('id')->each(function (object $groupe): void {
            DB::table('groupes')->where('id', $groupe->id)->update([
                'code' => 'GR-'.str_pad((string) $groupe->id, 4, '0', STR_PAD_LEFT),
            ]);
        });
        Schema::table('groupes', function (Blueprint $table) {
            $table->string('code', 20)->nullable(false)->change();
        });

        // ── 5. Supprimer unique(user_id) sur classe_etudiant ──────────────────
        //    Supprimé par fix_fk (MySQL-only) car un étudiant peut être
        //    dans plusieurs sections de cours différentes.
        Schema::table('classe_etudiant', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
        });

        // ── 6. Créer la nouvelle table classes (sections de cours) ─────────────
        //    Inclut les colonnes de add_schedule_and_number (migration MySQL-only).
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cours_id')->constrained('cours')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('nom', 100)->nullable();
            $table->string('numero', 5);
            $table->string('jour_semaine', 20)->nullable();
            $table->string('plage_horaire', 50)->nullable();
            $table->timestamps();

            $table->unique(['cours_id', 'numero']);
        });

        // ── 7. Créer une Classe par défaut pour chaque Cours ──────────────────
        $index = 1;
        DB::table('cours')->orderBy('id')->each(function (object $c) use (&$index): void {
            $code = ($c->groupe ?? '') !== '' ? $c->groupe : ('CL-'.str_pad((string) $c->id, 3, '0', STR_PAD_LEFT));
            DB::table('classes')->insert([
                'cours_id' => $c->id,
                'code' => $code,
                'numero' => str_pad((string) $index, 5, '0', STR_PAD_LEFT),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $index++;
        });

        // ── 8. groupes.classe_id : cours.id → classes.id (section par défaut) ─
        DB::table('groupes')->orderBy('id')->each(function (object $groupe): void {
            $classe = DB::table('classes')->where('cours_id', $groupe->classe_id)->first();
            if ($classe) {
                DB::table('groupes')->where('id', $groupe->id)->update(['classe_id' => $classe->id]);
            }
        });

        // ── 9. classe_etudiant.classe_id : cours.id → classes.id ─────────────
        DB::table('classe_etudiant')->orderBy('id')->each(function (object $pivot): void {
            $classe = DB::table('classes')->where('cours_id', $pivot->classe_id)->first();
            if ($classe) {
                DB::table('classe_etudiant')->where('id', $pivot->id)->update(['classe_id' => $classe->id]);
            }
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        Schema::dropIfExists('classes');

        Schema::table('echeancier_etapes', function (Blueprint $table) {
            $table->renameColumn('cours_id', 'classe_id');
        });

        Schema::table('cours_documents', function (Blueprint $table) {
            $table->renameColumn('cours_id', 'classe_id');
        });
        Schema::rename('cours_documents', 'classe_documents');

        Schema::rename('cours', 'classes');
    }
};
