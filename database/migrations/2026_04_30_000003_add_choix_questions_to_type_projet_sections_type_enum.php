<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Étend l'enum type de type_projet_sections avec la valeur choix_questions (Sprint 20).
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE type_projet_sections MODIFY type ENUM('texte', 'paragraphes', 'individuel', 'entrevue', 'video', 'audio', 'choix_questions') NOT NULL DEFAULT 'texte'");
        }
        // SQLite ne valide pas les ENUMs (stockés en TEXT) — aucune modification nécessaire.
    }

    /**
     * Retire choix_questions de l'enum.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE type_projet_sections MODIFY type ENUM('texte', 'paragraphes', 'individuel', 'entrevue', 'video', 'audio') NOT NULL DEFAULT 'texte'");
        }
        // SQLite : no-op
    }
};
