<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crée la table des critères de correction dans les gabarits de cours.
 *
 * Miroir de type_projet_criteres, mais rattaché à gabarit_types_projets
 * et gabarit_types_projets_sections plutôt qu'aux types de projets d'un cours réel.
 *
 * Ces critères sont copiés vers type_projet_criteres lors de l'application
 * d'un gabarit à un nouveau cours.
 */
return new class extends Migration
{
    /**
     * Crée la table gabarit_type_projet_criteres.
     */
    public function up(): void
    {
        Schema::create('gabarit_type_projet_criteres', function (Blueprint $table) {
            $table->id();

            $table->foreignId('gabarit_type_projet_id')
                ->constrained('gabarit_types_projets')
                ->cascadeOnDelete();

            // null = critère global (affiché avant les sections)
            $table->foreignId('gabarit_section_id')
                ->nullable()
                ->constrained('gabarit_types_projets_sections')
                ->cascadeOnDelete();

            // 'positif' : points accordés | 'negatif' : points déduits
            $table->string('type');

            // 'texte' : HTML libre | 'echelle' : grille de niveaux JSON
            $table->string('contenu_type')->default('texte');

            $table->decimal('pointage', 6, 2);

            $table->longText('contenu')->nullable();

            // Format : [{"label": "Excellent", "points": 5, "description": "..."}, ...]
            $table->json('echelle')->nullable();

            // Si vrai, l'étudiant voit ce critère lors de la rédaction
            $table->boolean('visible')->default(true);

            $table->unsignedInteger('ordre')->default(0);

            $table->timestamps();

            $table->index(
                ['gabarit_type_projet_id', 'gabarit_section_id', 'ordre'],
                'gabarit_criteres_type_section_ordre_idx'
            );
        });
    }

    /**
     * Supprime la table gabarit_type_projet_criteres.
     */
    public function down(): void
    {
        Schema::dropIfExists('gabarit_type_projet_criteres');
    }
};
