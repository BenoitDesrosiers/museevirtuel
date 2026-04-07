<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projets_recherche', function (Blueprint $table) {
            // Remplacer l'unique sur groupe_id seul par un unique composite
            // pour permettre plusieurs projets par groupe (un par TypeProjet)
            $table->dropUnique('projets_recherche_groupe_id_unique');
            $table->unique(['groupe_id', 'type_projet_id']);
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
