<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crée la table des liens d'entrevue partagés par l'enseignant dans un cours (Sprint 21).
     */
    public function up(): void
    {
        Schema::create('cours_liens_entrevue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cours_id')->constrained('cours')->cascadeOnDelete();
            $table->string('label');
            $table->string('url');
            $table->unsignedInteger('ordre')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Supprime la table cours_liens_entrevue.
     */
    public function down(): void
    {
        Schema::dropIfExists('cours_liens_entrevue');
    }
};
