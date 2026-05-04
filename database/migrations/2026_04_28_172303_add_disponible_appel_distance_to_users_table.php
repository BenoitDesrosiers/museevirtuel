<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajoute la disponibilité en appel à distance sur users (personnes âgées).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('disponible_appel_distance')->default(false)->after('signature_electronique');
        });
    }

    /**
     * Supprime la colonne disponible_appel_distance.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('disponible_appel_distance');
        });
    }
};
