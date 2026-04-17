<?php

use App\Models\Etablissement;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('thematiques', function (Blueprint $table) {
            $table->foreignId('etablissement_id')
                ->nullable()
                ->after('enseignant_id')
                ->constrained('etablissements')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('thematiques', function (Blueprint $table) {
            $table->dropForeignIdFor(Etablissement::class);
            $table->dropColumn('etablissement_id');
        });
    }
};
