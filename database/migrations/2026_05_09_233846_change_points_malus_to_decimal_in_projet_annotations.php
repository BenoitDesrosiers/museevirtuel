<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Convertit points_malus de unsigned tinyint à decimal(5,2)
     * pour permettre des déductions à virgule (ex. 0.5, 0.25).
     */
    public function up(): void
    {
        Schema::table('projet_annotations', function (Blueprint $table) {
            $table->decimal('points_malus', 5, 2)->nullable()->change();
        });
    }

    /**
     * Rétablit le type unsigned tinyint d'origine.
     */
    public function down(): void
    {
        Schema::table('projet_annotations', function (Blueprint $table) {
            $table->unsignedTinyInteger('points_malus')->nullable()->change();
        });
    }
};
