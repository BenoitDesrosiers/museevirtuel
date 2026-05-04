<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crée la table des objectifs pédagogiques d'un cours.
     */
    public function up(): void
    {
        Schema::create('cours_objectifs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cours_id')->constrained('cours')->cascadeOnDelete();
            $table->text('contenu');
            $table->unsignedInteger('ordre')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Supprime la table cours_objectifs.
     */
    public function down(): void
    {
        Schema::dropIfExists('cours_objectifs');
    }
};
