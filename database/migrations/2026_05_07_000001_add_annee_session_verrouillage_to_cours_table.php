<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajoute l'année académique, la session et le statut de verrouillage aux cours.
     */
    public function up(): void
    {
        Schema::table('cours', function (Blueprint $table) {
            $table->unsignedSmallInteger('annee')->default(2026)->after('groupe');
            $table->string('session')->default('hiver')->after('annee');
            $table->boolean('is_verrouille')->default(false)->after('session');
        });
    }

    /**
     * Supprime les colonnes ajoutées.
     */
    public function down(): void
    {
        Schema::table('cours', function (Blueprint $table) {
            $table->dropColumn(['annee', 'session', 'is_verrouille']);
        });
    }
};
