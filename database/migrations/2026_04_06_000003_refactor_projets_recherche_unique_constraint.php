<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projets_recherche', function (Blueprint $table) {
            // En MySQL, l'index unique sur groupe_id supporte la FK — il faut la supprimer avant de toucher à l'index
            $table->dropForeign(['groupe_id']);
            $table->dropUnique('projets_recherche_groupe_id_unique');
            $table->unique(['groupe_id', 'type_projet_id']);
            // Recréer la FK (le composite unique sert maintenant d'index pour la FK)
            $table->foreign('groupe_id')->references('id')->on('groupes')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projets_recherche', function (Blueprint $table) {
            $table->dropUnique(['groupe_id', 'type_projet_id']);
            $table->unique('groupe_id');
        });
    }
};
