<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crée les tables pour les références bibliographiques des cours.
 *
 * cours_references          → références associées à un cours spécifique
 * gabarit_cours_references  → références pré-définies dans un gabarit de cours
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─── Références bibliographiques d'un cours ───────────────────────────
        Schema::create('cours_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cours_id')->constrained('cours')->cascadeOnDelete();
            $table->string('nom');
            $table->string('url')->nullable();
            $table->unsignedInteger('ordre')->default(0);
            $table->timestamps();
        });

        // ─── Références bibliographiques d'un gabarit de cours ────────────────
        Schema::create('gabarit_cours_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gabarit_cours_id')->constrained('gabarit_cours')->cascadeOnDelete();
            $table->string('nom');
            $table->string('url')->nullable();
            $table->unsignedInteger('ordre')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gabarit_cours_references');
        Schema::dropIfExists('cours_references');
    }
};
