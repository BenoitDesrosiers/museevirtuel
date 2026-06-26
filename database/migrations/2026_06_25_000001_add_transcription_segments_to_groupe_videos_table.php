<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groupe_videos', function (Blueprint $table) {
            // Segments horodatés retournés par Whisper en mode verbose_json.
            // Chaque segment : { start: float, end: float, text: string }.
            // Null pour les transcriptions générées avant cette migration.
            $table->json('transcription_segments')->nullable()->after('transcription');
        });
    }

    public function down(): void
    {
        Schema::table('groupe_videos', function (Blueprint $table) {
            $table->dropColumn('transcription_segments');
        });
    }
};
