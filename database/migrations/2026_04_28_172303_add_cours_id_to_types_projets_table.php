<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajoute cours_id, ponderation et is_sommatif sur types_projets.
     * Effectue un backfill : associe chaque TypeProjet au premier cours de son enseignant.
     */
    public function up(): void
    {
        Schema::table('types_projets', function (Blueprint $table) {
            $table->foreignId('cours_id')->nullable()->after('enseignant_id')->constrained('cours')->nullOnDelete();
            $table->decimal('ponderation', 5, 2)->nullable()->after('cours_id');
            $table->boolean('is_sommatif')->default(true)->after('ponderation');
        });

        // Backfill : associer chaque TypeProjet au premier cours de son enseignant
        DB::table('types_projets')->whereNull('cours_id')->get()->each(function (object $tp) {
            $cours = DB::table('cours')->where('enseignant_id', $tp->enseignant_id)->first();
            if ($cours) {
                DB::table('types_projets')->where('id', $tp->id)->update(['cours_id' => $cours->id]);
            }
        });
    }

    /**
     * Supprime les colonnes ajoutées.
     */
    public function down(): void
    {
        Schema::table('types_projets', function (Blueprint $table) {
            $table->dropForeign(['cours_id']);
            $table->dropColumn(['cours_id', 'ponderation', 'is_sommatif']);
        });
    }
};
