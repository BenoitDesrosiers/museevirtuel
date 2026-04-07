<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('type_projet_sections', function (Blueprint $table) {
            $table->enum('type', ['texte', 'paragraphes', 'individuel'])
                ->default('texte')
                ->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('type_projet_sections', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
