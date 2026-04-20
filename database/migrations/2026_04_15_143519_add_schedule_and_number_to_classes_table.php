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
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('classes', function (Blueprint $table) {
            $table->string('numero', 5)->nullable()->after('cours_id');
            $table->string('jour_semaine', 20)->nullable()->after('nom');
            $table->string('plage_horaire', 50)->nullable()->after('jour_semaine');
        });

        DB::table('classes')
            ->orderBy('id')
            ->get(['id'])
            ->each(function (object $classe, int $index): void {
                DB::table('classes')
                    ->where('id', $classe->id)
                    ->update(['numero' => str_pad((string) ($index + 1), 5, '0', STR_PAD_LEFT)]);
            });

        Schema::table('classes', function (Blueprint $table) {
            $table->string('numero', 5)->nullable(false)->change();
            $table->unique(['cours_id', 'numero']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('classes', function (Blueprint $table) {
            $table->dropUnique('classes_cours_id_numero_unique');
            $table->dropColumn(['numero', 'jour_semaine', 'plage_horaire']);
        });
    }
};
