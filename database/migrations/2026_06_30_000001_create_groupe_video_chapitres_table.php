<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groupe_video_chapitres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_id')->constrained('groupe_videos')->cascadeOnDelete();
            $table->string('label');
            $table->float('debut'); // secondes
            $table->float('fin')->nullable(); // null = jusqu'à la fin de la vidéo
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();

            $table->index(['video_id', 'debut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('groupe_video_chapitres');
    }
};
