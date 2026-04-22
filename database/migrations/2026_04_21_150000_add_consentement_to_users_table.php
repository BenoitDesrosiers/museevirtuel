<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajoute les colonnes de consentement électronique pour les personnes âgées.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('engagements_acceptes_le')->nullable()->after('theme_libre');
            $table->text('signature_electronique')->nullable()->after('engagements_acceptes_le');
        });
    }

    /**
     * Supprime les colonnes de consentement électronique.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['engagements_acceptes_le', 'signature_electronique']);
        });
    }
};
