<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crée la table des coches personnelles d'étudiants sur les critères visibles.
 *
 * Quand un critère est marqué `visible = true`, l'étudiant peut le cocher
 * comme indicateur personnel pendant la rédaction de son projet.
 * Cette coche n'influence PAS la correction — c'est un outil de suivi personnel.
 *
 * La présence d'un enregistrement signifie « coché » ; son absence signifie « non coché ».
 * Le toggle s'effectue par création ou suppression du record.
 */
return new class extends Migration
{
    /**
     * Crée la table projet_critere_coches.
     */
    public function up(): void
    {
        Schema::create('projet_critere_coches', function (Blueprint $table) {
            $table->id();

            $table->foreignId('projet_id')
                ->constrained('projets_recherche')
                ->cascadeOnDelete();

            $table->foreignId('critere_id')
                ->constrained('type_projet_criteres')
                ->cascadeOnDelete();

            // L'étudiant qui a coché ce critère.
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->timestamps();

            // Un étudiant ne peut cocher un critère qu'une seule fois par projet.
            $table->unique(['projet_id', 'critere_id', 'user_id'], 'coches_projet_critere_user_unique');
        });
    }

    /**
     * Supprime la table projet_critere_coches.
     */
    public function down(): void
    {
        Schema::dropIfExists('projet_critere_coches');
    }
};
