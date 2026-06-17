<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crée la table des critères de correction définis par l'enseignant.
 *
 * Un critère appartient à un TypeProjet et, optionnellement, à une section précise.
 * Un critère sans section (section_id = null) est considéré comme « global » :
 * il est affiché avant les sections dans la vue de correction.
 *
 * Deux types de critères :
 *  - positif : les points sont retenus par défaut et accordés quand l'enseignant coche le critère.
 *  - negatif : les points sont déduits du total quand l'enseignant applique le critère.
 *
 * Le contenu d'un critère est soit du texte libre (HTML), soit une échelle de pointage
 * stockée en JSON sous la forme [{label, points, description?}, ...].
 */
return new class extends Migration
{
    /**
     * Crée la table type_projet_criteres.
     */
    public function up(): void
    {
        Schema::create('type_projet_criteres', function (Blueprint $table) {
            $table->id();

            // Le critère appartient toujours à un TypeProjet (suppression en cascade).
            $table->foreignId('type_projet_id')
                ->constrained('types_projets')
                ->cascadeOnDelete();

            // La section est optionnelle : null = critère global (avant les sections).
            $table->foreignId('section_id')
                ->nullable()
                ->constrained('type_projet_sections')
                ->cascadeOnDelete();

            // 'positif' : points accordés à la vérification.
            // 'negatif' : points déduits à l'application.
            $table->string('type');

            // 'texte' : description en HTML.
            // 'echelle' : grille de niveaux stockée dans la colonne `echelle`.
            $table->string('contenu_type')->default('texte');

            // Valeur en points du critère (ex. : 5.00 pour un critère valant 5 points).
            $table->decimal('pointage', 6, 2);

            // Contenu texte libre (HTML) — utilisé quand contenu_type = 'texte'.
            $table->longText('contenu')->nullable();

            // Échelle de niveaux — utilisée quand contenu_type = 'echelle'.
            // Format : [{"label": "Excellent", "points": 5, "description": "..."}, ...]
            // La somme des points de l'échelle doit égaler `pointage` (validé en UI).
            $table->json('echelle')->nullable();

            // Si vrai, l'étudiant voit ce critère et son pointage lors de la rédaction.
            // L'étudiant peut le cocher comme indicateur personnel (sans impact sur la note).
            $table->boolean('visible')->default(true);

            $table->unsignedInteger('ordre')->default(0);

            $table->timestamps();

            // Index composite pour les requêtes de chargement par type de projet et section.
            $table->index(['type_projet_id', 'section_id', 'ordre'], 'criteres_type_section_ordre_idx');
        });
    }

    /**
     * Supprime la table type_projet_criteres.
     */
    public function down(): void
    {
        Schema::dropIfExists('type_projet_criteres');
    }
};
