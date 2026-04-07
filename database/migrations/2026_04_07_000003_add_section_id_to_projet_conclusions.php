<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projet_conclusions', function (Blueprint $table) {
            $table->foreignId('section_id')
                ->nullable()
                ->after('user_id')
                ->constrained('type_projet_sections')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projet_conclusions', function (Blueprint $table) {
            $table->dropForeign(['section_id']);
            $table->dropColumn('section_id');
        });
    }
};
