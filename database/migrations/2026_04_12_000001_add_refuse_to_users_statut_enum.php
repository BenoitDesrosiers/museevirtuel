<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL : étendre l'enum statut pour ajouter refuse
        // SQLite (tests) : pas d'enum natif, la colonne string accepte déjà toute valeur
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN statut ENUM('actif','en_attente','refuse') NOT NULL DEFAULT 'actif'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN statut ENUM('actif','en_attente') NOT NULL DEFAULT 'actif'");
        }
    }
};
