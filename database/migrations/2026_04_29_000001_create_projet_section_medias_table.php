<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crée la table des médias (vidéo/audio) attachés aux sections de projet.
     */
    public function up(): void
    {
        Schema::create('projet_section_medias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('projet_id')->constrained('projets_recherche')->cascadeOnDelete();
            $table->foreignId('section_id')->constrained('type_projet_sections')->cascadeOnDelete();
            $table->string('type'); // video | audio
            $table->string('source_type'); // upload | url
            $table->string('url')->nullable();
            $table->string('file_path')->nullable();
            $table->string('nom_original')->nullable();
            $table->unsignedBigInteger('taille')->nullable();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Supprime la table projet_section_medias.
     */
    public function down(): void
    {
        Schema::dropIfExists('projet_section_medias');
    }
};
