<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groupe_videos', function (Blueprint $table) {
            // Texte brut retourné par Whisper.
            $table->text('transcription')->nullable()->after('description');

            // Cycle de vie identique au traitement_statut FFmpeg.
            $table->string('transcription_statut')->nullable()->after('transcription');
        });
    }

    public function down(): void
    {
        Schema::table('groupe_videos', function (Blueprint $table) {
            $table->dropColumn(['transcription', 'transcription_statut']);
        });
    }
};
