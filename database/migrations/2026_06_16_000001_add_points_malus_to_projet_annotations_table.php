<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projet_annotations', function (Blueprint $table) {
            $table->decimal('points_malus', 5, 2)->nullable()->after('mot_annote');
        });
    }

    public function down(): void
    {
        Schema::table('projet_annotations', function (Blueprint $table) {
            $table->dropColumn('points_malus');
        });
    }
};
