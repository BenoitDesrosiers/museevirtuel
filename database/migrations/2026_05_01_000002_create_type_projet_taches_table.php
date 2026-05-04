<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crée la table des tâches définies par l'enseignant dans un TypeProjet (Sprint 21).
     */
    public function up(): void
    {
        Schema::create('type_projet_taches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('type_projet_id')->constrained('types_projets')->cascadeOnDelete();
            $table->string('titre');
            $table->text('description')->nullable();
            $table->unsignedInteger('ordre')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Supprime la table type_projet_taches.
     */
    public function down(): void
    {
        Schema::dropIfExists('type_projet_taches');
    }
};
