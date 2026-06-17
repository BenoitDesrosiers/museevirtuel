<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute la colonne pointage sur type_projet_sections.
 *
 * Chaque section peut se voir attribuer un nombre de points maximal.
 * Le total des critères positifs d'une section ne doit pas dépasser ce pointage.
 */
return new class extends Migration
{
    /**
     * Ajoute la colonne pointage (nullable — une section sans pointage est non évaluée).
     */
    public function up(): void
    {
        Schema::table('type_projet_sections', function (Blueprint $table) {
            $table->decimal('pointage', 6, 2)->nullable()->after('type');
        });
    }

    /**
     * Retire la colonne pointage.
     */
    public function down(): void
    {
        Schema::table('type_projet_sections', function (Blueprint $table) {
            $table->dropColumn('pointage');
        });
    }
};
