<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crée la table pivot entre un projet et les questions choisies par l'équipe.
     */
    public function up(): void
    {
        Schema::create('projet_questions_choisies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('projet_id')->constrained('projets_recherche')->cascadeOnDelete();
            $table->foreignId('section_id')->constrained('type_projet_sections')->cascadeOnDelete();
            $table->foreignId('question_banque_id')->constrained('question_banques')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['projet_id', 'question_banque_id']);
        });
    }

    /**
     * Supprime la table projet_questions_choisies.
     */
    public function down(): void
    {
        Schema::dropIfExists('projet_questions_choisies');
    }
};
