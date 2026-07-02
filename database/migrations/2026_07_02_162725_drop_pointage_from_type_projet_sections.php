<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Supprime la colonne pointage de type_projet_sections.
     *
     * Ce champ était purement indicatif et pouvait être dépassé librement
     * par les critères — il a donc été jugé inutile et supprimé.
     */
    public function up(): void
    {
        Schema::table('type_projet_sections', function (Blueprint $table) {
            $table->dropColumn('pointage');
        });
    }

    /**
     * Recrée la colonne pointage en cas de rollback.
     */
    public function down(): void
    {
        Schema::table('type_projet_sections', function (Blueprint $table) {
            $table->decimal('pointage', 8, 2)->nullable()->after('type');
        });
    }
};
