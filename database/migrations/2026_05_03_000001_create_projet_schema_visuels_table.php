<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crée la table de stockage des schémas visuels DEP (Sprint 24).
     *
     * Une entrée par (projet, section) — upsert côté controller.
     * Le champ contenu JSON stocke l'image centrale + les 3 zones de cartes.
     */
    public function up(): void
    {
        Schema::create('projet_schema_visuels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('projet_id')->constrained('projets_recherche')->cascadeOnDelete();
            $table->foreignId('section_id')->constrained('type_projet_sections')->cascadeOnDelete();
            $table->json('contenu');
            $table->timestamps();

            $table->unique(['projet_id', 'section_id']);
        });
    }

    /**
     * Supprime la table projet_schema_visuels.
     */
    public function down(): void
    {
        Schema::dropIfExists('projet_schema_visuels');
    }
};
