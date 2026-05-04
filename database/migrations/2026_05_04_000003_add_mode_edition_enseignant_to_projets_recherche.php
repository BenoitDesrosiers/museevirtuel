<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute le drapeau mode_edition_enseignant à la table projets_recherche.
 *
 * Quand true, l'enseignant du cours peut modifier directement le contenu du projet
 * de l'équipe (titre, sections, développements, conclusions, renvois) sans être membre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projets_recherche', function (Blueprint $table) {
            $table->boolean('mode_edition_enseignant')->default(false)->after('verrouille');
        });
    }

    public function down(): void
    {
        Schema::table('projets_recherche', function (Blueprint $table) {
            $table->dropColumn('mode_edition_enseignant');
        });
    }
};
