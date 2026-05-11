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
        Schema::table('projet_renvois', function (Blueprint $table) {
            $table->string('type_reference', 50)->nullable()->after('contenu');
            $table->json('champs_reference')->nullable()->after('type_reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projet_renvois', function (Blueprint $table) {
            $table->dropColumn(['type_reference', 'champs_reference']);
        });
    }
};
