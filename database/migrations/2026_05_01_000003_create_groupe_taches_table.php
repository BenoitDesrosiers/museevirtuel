<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crée la table de suivi des tâches par groupe : assignation et complétion (Sprint 21).
     */
    public function up(): void
    {
        Schema::create('groupe_taches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tache_id')->constrained('type_projet_taches')->cascadeOnDelete();
            $table->foreignId('groupe_id')->constrained('groupes')->cascadeOnDelete();
            $table->foreignId('assigne_a')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['tache_id', 'groupe_id']);
        });
    }

    /**
     * Supprime la table groupe_taches.
     */
    public function down(): void
    {
        Schema::dropIfExists('groupe_taches');
    }
};
