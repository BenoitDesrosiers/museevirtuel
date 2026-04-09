<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entrevue_lignes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('concept_id')->constrained('entrevue_concepts')->cascadeOnDelete();
            $table->text('dimension')->nullable();
            $table->text('indicateur')->nullable();
            $table->json('questions')->nullable();
            $table->unsignedInteger('ordre');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entrevue_lignes');
    }
};
