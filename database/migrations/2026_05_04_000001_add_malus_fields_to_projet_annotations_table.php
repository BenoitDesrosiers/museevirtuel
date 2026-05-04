<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute les champs de malus inline aux annotations de correction.
 *
 * cible_user_id : l'étudiant visé par la déduction (null = non spécifié)
 * points_malus  : nombre de points à retirer (null = aucun malus associé)
 *
 * Ces champs ne sont pertinents que pour les annotations de type 'correction'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projet_annotations', function (Blueprint $table) {
            $table->foreignId('cible_user_id')
                ->nullable()
                ->after('user_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->unsignedTinyInteger('points_malus')
                ->nullable()
                ->after('cible_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('projet_annotations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cible_user_id');
            $table->dropColumn('points_malus');
        });
    }
};
