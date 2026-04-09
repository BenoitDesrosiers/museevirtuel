<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('types_projets', function (Blueprint $table) {
            $table->datetime('date_remise')->nullable()->after('accessible');
            $table->boolean('remises_multiples')->default(false)->after('date_remise');
            $table->boolean('retard_permis')->default(false)->after('remises_multiples');
        });
    }

    public function down(): void
    {
        Schema::table('types_projets', function (Blueprint $table) {
            $table->dropColumn(['date_remise', 'remises_multiples', 'retard_permis']);
        });
    }
};
