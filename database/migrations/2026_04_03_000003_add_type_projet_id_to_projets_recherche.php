<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('projets_recherche', 'type_projet_id')) {
            Schema::table('projets_recherche', function (Blueprint $table): void {
                $table->foreignId('type_projet_id')
                    ->nullable()
                    ->after('groupe_id')
                    ->constrained('types_projets')
                    ->nullOnDelete();
            });
        }

        // Rattacher chaque projet existant au TypeProjet "Projet de recherche"
        // de l'enseignant de la classe du groupe (compatible SQLite + MySQL)
        $projets = DB::table('projets_recherche')
            ->join('groupes', 'projets_recherche.groupe_id', '=', 'groupes.id')
            ->join('classes', 'groupes.classe_id', '=', 'classes.id')
            ->join('types_projets', function ($join): void {
                $join->on('types_projets.enseignant_id', '=', 'classes.enseignant_id')
                    ->where('types_projets.nom', '=', 'Projet de recherche');
            })
            ->select('projets_recherche.id as projet_id', 'types_projets.id as type_projet_id')
            ->get();

        foreach ($projets as $row) {
            DB::table('projets_recherche')
                ->where('id', $row->projet_id)
                ->update(['type_projet_id' => $row->type_projet_id]);
        }
    }

    public function down(): void
    {
        Schema::table('projets_recherche', function (Blueprint $table): void {
            $table->dropForeign(['type_projet_id']);
            $table->dropColumn('type_projet_id');
        });
    }
};
