<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La contrainte UNIQUE(projet_id, user_id) empêche plusieurs conclusions
     * pour le même étudiant dans le même projet mais sur des sections différentes.
     * On la remplace par une contrainte sur (projet_id, user_id, section_id)
     * pour supporter les sections de type 'individuel'.
     */
    public function up(): void
    {
        Schema::table('projet_conclusions', function (Blueprint $table) {
            $table->dropUnique(['projet_id', 'user_id']);
            $table->unique(['projet_id', 'user_id', 'section_id']);
        });
    }

    public function down(): void
    {
        Schema::table('projet_conclusions', function (Blueprint $table) {
            $table->dropUnique(['projet_id', 'user_id', 'section_id']);
            $table->unique(['projet_id', 'user_id']);
        });
    }
};
