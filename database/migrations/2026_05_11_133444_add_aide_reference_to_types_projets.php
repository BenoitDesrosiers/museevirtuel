<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('types_projets', function (Blueprint $table) {
            $table->boolean('aide_reference')->default(false)->after('generer_table_matieres');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('types_projets', function (Blueprint $table) {
            $table->dropColumn('aide_reference');
        });
    }
};
