<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projet_section_paragraphes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('projet_id')->constrained('projets_recherche')->cascadeOnDelete();
            $table->foreignId('section_id')->constrained('type_projet_sections')->cascadeOnDelete();
            $table->unsignedInteger('ordre');
            $table->text('titre')->nullable();
            $table->longText('contenu')->nullable();
            $table->timestamps();

            $table->index(['projet_id', 'section_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projet_section_paragraphes');
    }
};
