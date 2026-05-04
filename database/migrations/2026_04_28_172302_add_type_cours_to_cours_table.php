<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajoute le type de cours et les contraintes de taille d'équipe.
     */
    public function up(): void
    {
        Schema::table('cours', function (Blueprint $table) {
            $table->string('type_cours')->default('cours_complet')->after('enseignant_id');
            $table->tinyInteger('taille_equipe_min')->unsigned()->nullable()->after('type_cours');
            $table->tinyInteger('taille_equipe_max')->unsigned()->nullable()->after('taille_equipe_min');
        });
    }

    /**
     * Supprime les colonnes ajoutées.
     */
    public function down(): void
    {
        Schema::table('cours', function (Blueprint $table) {
            $table->dropColumn(['type_cours', 'taille_equipe_min', 'taille_equipe_max']);
        });
    }
};
