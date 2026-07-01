<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groupe_videos', function (Blueprint $table) {
            // Indique que la transcription a été éditée manuellement.
            // Permet d'avertir l'utilisateur avant une re-génération Whisper
            // qui écraserait ses corrections.
            $table->boolean('transcription_modifiee')->default(false)->after('transcription_statut');
        });
    }

    public function down(): void
    {
        Schema::table('groupe_videos', function (Blueprint $table) {
            $table->dropColumn('transcription_modifiee');
        });
    }
};
