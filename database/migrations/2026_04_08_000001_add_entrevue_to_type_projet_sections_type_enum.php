<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE type_projet_sections MODIFY type ENUM('texte', 'paragraphes', 'individuel', 'entrevue') NOT NULL DEFAULT 'texte'");
        } else {
            // SQLite : reconstruire la colonne avec la valeur étendue (la table est vide en tests)
            Schema::table('type_projet_sections', function (Blueprint $table) {
                $table->dropColumn('type');
            });
            Schema::table('type_projet_sections', function (Blueprint $table) {
                $table->enum('type', ['texte', 'paragraphes', 'individuel', 'entrevue'])
                    ->default('texte')
                    ->after('description');
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE type_projet_sections MODIFY type ENUM('texte', 'paragraphes', 'individuel') NOT NULL DEFAULT 'texte'");
        } else {
            Schema::table('type_projet_sections', function (Blueprint $table) {
                $table->dropColumn('type');
            });
            Schema::table('type_projet_sections', function (Blueprint $table) {
                $table->enum('type', ['texte', 'paragraphes', 'individuel'])
                    ->default('texte')
                    ->after('description');
            });
        }
    }
};
