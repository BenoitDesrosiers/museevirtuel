<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crée la table des visioconférences Jitsi (Sprint 23).
     */
    public function up(): void
    {
        Schema::create('visio_conferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cours_id')->constrained('cours')->cascadeOnDelete();
            $table->foreignId('groupe_id')->nullable()->constrained('groupes')->nullOnDelete();
            $table->foreignId('animateur_id')->constrained('users')->cascadeOnDelete();
            $table->string('jitsi_room')->unique();
            $table->string('titre');
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->string('recording_url')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Supprime la table visio_conferences.
     */
    public function down(): void
    {
        Schema::dropIfExists('visio_conferences');
    }
};
