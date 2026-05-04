<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crée la table des consentements vidéo signés (étudiants et personnes âgées).
     */
    public function up(): void
    {
        Schema::create('consentement_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('projet_id')->nullable()->constrained('projets_recherche')->nullOnDelete();
            $table->string('type'); // etudiant | personne_agee
            $table->boolean('accepte')->default(false);
            $table->text('signature')->nullable(); // base64 PNG
            $table->dateTime('signed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Supprime la table consentement_videos.
     */
    public function down(): void
    {
        Schema::dropIfExists('consentement_videos');
    }
};
