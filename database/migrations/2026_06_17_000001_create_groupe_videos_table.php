<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groupe_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('groupe_id')->constrained('groupes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('titre');
            $table->text('description')->nullable();
            $table->string('nom_original');
            $table->string('file_path');
            $table->unsignedBigInteger('taille'); // bytes
            $table->unsignedSmallInteger('duree')->nullable(); // secondes
            $table->string('thumbnail_path')->nullable();
            $table->enum('statut', ['brouillon', 'publié', 'archivé'])->default('brouillon');
            $table->string('traitement_statut')->nullable(); // en_attente|en_cours|terminé|erreur
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('groupe_videos');
    }
};
