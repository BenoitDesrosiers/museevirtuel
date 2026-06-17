<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projet_annotations', function (Blueprint $table) {
            // null = tous les étudiants du groupe ; un user_id = un étudiant spécifique
            $table->unsignedBigInteger('cible_user_id')->nullable()->after('points_malus');
            $table->foreign('cible_user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('projet_annotations', function (Blueprint $table) {
            $table->dropForeign(['cible_user_id']);
            $table->dropColumn('cible_user_id');
        });
    }
};
