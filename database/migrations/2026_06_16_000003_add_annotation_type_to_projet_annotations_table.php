<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projet_annotations', function (Blueprint $table) {
            // 'commentaire' = remarque sans impact sur la note ; 'correction' = peut déduire des points
            $table->enum('annotation_type', ['commentaire', 'correction'])
                ->default('commentaire')
                ->after('cible_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('projet_annotations', function (Blueprint $table) {
            $table->dropColumn('annotation_type');
        });
    }
};
