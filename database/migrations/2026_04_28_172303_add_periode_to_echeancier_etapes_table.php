<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajoute la colonne periode (1 ou 2) sur echeancier_etapes.
     */
    public function up(): void
    {
        Schema::table('echeancier_etapes', function (Blueprint $table) {
            $table->tinyInteger('periode')->unsigned()->nullable()->after('semaine');
        });
    }

    /**
     * Supprime la colonne periode.
     */
    public function down(): void
    {
        Schema::table('echeancier_etapes', function (Blueprint $table) {
            $table->dropColumn('periode');
        });
    }
};
