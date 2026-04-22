<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rend enseignant_id nullable : les thématiques appartiennent désormais
     * à un établissement, pas à un enseignant individuel.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE thematiques MODIFY enseignant_id BIGINT UNSIGNED NULL');
        } else {
            Schema::table('thematiques', function (Blueprint $table) {
                $table->unsignedBigInteger('enseignant_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE thematiques MODIFY enseignant_id BIGINT UNSIGNED NOT NULL');
        } else {
            Schema::table('thematiques', function (Blueprint $table) {
                $table->unsignedBigInteger('enseignant_id')->nullable(false)->change();
            });
        }
    }
};
