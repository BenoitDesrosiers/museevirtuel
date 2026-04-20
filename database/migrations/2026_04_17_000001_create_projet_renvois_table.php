<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projet_renvois', function (Blueprint $table) {
            $table->id();
            $table->foreignId('projet_id')->constrained('projets_recherche')->cascadeOnDelete();
            $table->unsignedSmallInteger('numero');
            $table->text('contenu')->nullable();
            $table->timestamps();

            $table->unique(['projet_id', 'numero']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projet_renvois');
    }
};
