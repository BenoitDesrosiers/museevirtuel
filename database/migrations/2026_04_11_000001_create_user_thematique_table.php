<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Table pivot user ↔ thématique (many-to-many pour les PA)
        Schema::create('user_thematique', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('thematique_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'thematique_id']);
        });

        // Colonne provenance du témoin (ville, région…)
        Schema::table('users', function (Blueprint $table) {
            $table->string('provenance')->nullable()->after('description');
        });

        // Migrer les thematique_id existants vers la pivot
        DB::table('users')
            ->whereNotNull('thematique_id')
            ->where('role', 'personne_agee')
            ->orderBy('id')
            ->get(['id', 'thematique_id'])
            ->each(function ($user) {
                DB::table('user_thematique')->insertOrIgnore([
                    'user_id' => $user->id,
                    'thematique_id' => $user->thematique_id,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_thematique');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('provenance');
        });
    }
};
