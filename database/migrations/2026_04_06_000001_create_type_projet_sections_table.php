<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('type_projet_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('type_projet_id')->constrained('types_projets')->cascadeOnDelete();
            $table->string('label', 200);
            $table->text('description')->nullable();
            $table->unsignedInteger('ordre')->default(0);
            $table->timestamps();

            $table->index('type_projet_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('type_projet_sections');
    }
};
