<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projet_section_contenus', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('projet_id')->constrained('projets_recherche')->cascadeOnDelete();
            $table->foreignId('section_id')->constrained('type_projet_sections')->cascadeOnDelete();
            $table->longText('contenu')->nullable();
            $table->timestamps();

            $table->unique(['projet_id', 'section_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projet_section_contenus');
    }
};
