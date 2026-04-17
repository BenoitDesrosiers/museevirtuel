<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_etablissement', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('etablissement_id')
                ->constrained('etablissements')
                ->cascadeOnDelete();

            $table->text('theme_libre')->nullable();

            $table->primary(['user_id', 'etablissement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_etablissement');
    }
};
