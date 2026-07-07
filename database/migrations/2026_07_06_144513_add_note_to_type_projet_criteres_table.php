<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajoute la colonne `note` (visible uniquement par l'enseignant) sur les tables
     * de critères de correction et de gabarits.
     */
    public function up(): void
    {
        Schema::table('type_projet_criteres', function (Blueprint $table) {
            $table->text('note')->nullable()->after('contenu');
        });

        Schema::table('gabarit_type_projet_criteres', function (Blueprint $table) {
            $table->text('note')->nullable()->after('contenu');
        });
    }

    /**
     * Retire la colonne `note` des deux tables.
     */
    public function down(): void
    {
        Schema::table('type_projet_criteres', function (Blueprint $table) {
            $table->dropColumn('note');
        });

        Schema::table('gabarit_type_projet_criteres', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
};
