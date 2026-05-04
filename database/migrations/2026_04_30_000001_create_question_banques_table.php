<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crée la table des questions de la banque, rattachées à une section de TypeProjet.
     */
    public function up(): void
    {
        Schema::create('question_banques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('type_projet_sections')->cascadeOnDelete();
            $table->text('contenu');
            $table->unsignedInteger('ordre')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Supprime la table question_banques.
     */
    public function down(): void
    {
        Schema::dropIfExists('question_banques');
    }
};
