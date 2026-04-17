<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('types_projets', function (Blueprint $table) {
            $table->boolean('generer_page_titre')->default(true)->after('retard_permis');
            $table->boolean('generer_table_matieres')->default(true)->after('generer_page_titre');
        });
    }

    public function down(): void
    {
        Schema::table('types_projets', function (Blueprint $table) {
            $table->dropColumn(['generer_page_titre', 'generer_table_matieres']);
        });
    }
};
