<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Étend l'enum type de type_projet_sections avec les valeurs video et audio (Sprint 19).
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE type_projet_sections MODIFY type ENUM('texte', 'paragraphes', 'individuel', 'entrevue', 'video', 'audio') NOT NULL DEFAULT 'texte'");
        }
        // SQLite ne valide pas les ENUMs (stockés en TEXT) — aucune modification nécessaire.
    }

    /**
     * Retire les valeurs video et audio de l'enum.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE type_projet_sections MODIFY type ENUM('texte', 'paragraphes', 'individuel', 'entrevue') NOT NULL DEFAULT 'texte'");
        }
        // SQLite : no-op
    }
};
