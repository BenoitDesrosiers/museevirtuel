<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projets_recherche', function (Blueprint $table) {
            $table->string('statut', 30)->default('en_cours')->after('remis_le');
        });

        $projets = DB::table('projets_recherche as projets')
            ->leftJoin('types_projets as types', 'types.id', '=', 'projets.type_projet_id')
            ->select([
                'projets.id',
                'projets.remis_le',
                DB::raw('COALESCE(types.date_remise, projets.date_remise) as date_remise_effective'),
            ])
            ->get();

        $maintenant = now();

        foreach ($projets as $projet) {
            $dateRemise = $projet->date_remise_effective !== null
                ? now()->parse($projet->date_remise_effective)
                : null;
            $remisLe = $projet->remis_le !== null ? now()->parse($projet->remis_le) : null;

            $statut = $remisLe !== null
                ? ($dateRemise !== null && $remisLe->gt($dateRemise) ? 'remis_en_retard' : 'remis')
                : ($dateRemise !== null && $maintenant->gt($dateRemise) ? 'en_retard' : 'en_cours');

            DB::table('projets_recherche')
                ->where('id', $projet->id)
                ->update(['statut' => $statut]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projets_recherche', function (Blueprint $table) {
            $table->dropColumn('statut');
        });
    }
};
