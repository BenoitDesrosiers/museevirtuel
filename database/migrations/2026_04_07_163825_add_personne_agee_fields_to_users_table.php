<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL : étendre l'enum role pour ajouter personne_agee
        // SQLite (tests) : pas d'enum natif, la colonne string accepte déjà toute valeur
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','enseignant','etudiant','personne_agee') NOT NULL DEFAULT 'etudiant'");
        }

        Schema::table('users', function (Blueprint $table) {
            $table->enum('statut', ['actif', 'en_attente'])->default('actif')->after('role');
            $table->text('description')->nullable()->after('statut');
            $table->foreignId('thematique_id')->nullable()->constrained('thematiques')->nullOnDelete()->after('description');
            $table->text('theme_libre')->nullable()->after('thematique_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['thematique_id']);
            $table->dropColumn(['statut', 'description', 'thematique_id', 'theme_libre']);
        });

        // Restaurer l'enum role sans personne_agee (MySQL uniquement)
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','enseignant','etudiant') NOT NULL DEFAULT 'etudiant'");
        }
    }
};
