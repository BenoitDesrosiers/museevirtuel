<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Étape 1 : ajouter type_projet_id nullable
        Schema::table('grilles_correction', function (Blueprint $table): void {
            $table->foreignId('type_projet_id')
                ->nullable()
                ->after('id')
                ->constrained('types_projets')
                ->cascadeOnDelete();
        });

        // Étape 2 : pour chaque grille existante, créer un TypeProjet
        // "Projet de recherche" rattaché à l'enseignant de la classe
        $grilles = DB::table('grilles_correction')
            ->join('classes', 'grilles_correction.classe_id', '=', 'classes.id')
            ->select('grilles_correction.id as grille_id', 'classes.enseignant_id')
            ->get();

        // Réutiliser un TypeProjet par enseignant (éviter les doublons)
        $typeProjetParEnseignant = [];

        foreach ($grilles as $grille) {
            $enseignantId = $grille->enseignant_id;

            if (! isset($typeProjetParEnseignant[$enseignantId])) {
                $typeProjetId = DB::table('types_projets')->insertGetId([
                    'enseignant_id' => $enseignantId,
                    'nom' => 'Projet de recherche',
                    'description' => null,
                    'accessible' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $typeProjetParEnseignant[$enseignantId] = $typeProjetId;
            }

            DB::table('grilles_correction')
                ->where('id', $grille->grille_id)
                ->update(['type_projet_id' => $typeProjetParEnseignant[$enseignantId]]);
        }

        // Étape 3 : rendre type_projet_id NOT NULL + unique
        Schema::table('grilles_correction', function (Blueprint $table): void {
            $table->unsignedBigInteger('type_projet_id')->nullable(false)->change();
            $table->unique('type_projet_id');
        });

        // Étape 4 : supprimer classe_id
        Schema::table('grilles_correction', function (Blueprint $table): void {
            $table->dropForeign(['classe_id']);
            $table->dropUnique(['classe_id']);
            $table->dropColumn('classe_id');
        });
    }

    public function down(): void
    {
        Schema::table('grilles_correction', function (Blueprint $table): void {
            $table->dropUnique(['type_projet_id']);
            $table->dropForeign(['type_projet_id']);
            $table->dropColumn('type_projet_id');
            $table->foreignId('classe_id')
                ->after('id')
                ->unique()
                ->constrained('classes')
                ->cascadeOnDelete();
        });
    }
};
