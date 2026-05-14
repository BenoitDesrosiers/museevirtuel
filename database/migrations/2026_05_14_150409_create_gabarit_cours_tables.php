<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crée les tables système qui stockent les gabarits de cours.
 *
 * Un gabarit contient les objectifs, types de projets (avec sections)
 * et étapes d'échéancier qui peuvent être copiés vers un nouveau cours.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─── Gabarits de cours ────────────────────────────────────────────────
        Schema::create('gabarit_cours', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();          // ex: 'cours_complet'
            $table->string('type_cours');              // valeur de l'enum TypeCours
            $table->string('nom');                     // nom lisible du gabarit
            $table->timestamps();
        });

        // ─── Objectifs pédagogiques du gabarit ───────────────────────────────
        Schema::create('gabarit_cours_objectifs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gabarit_cours_id')->constrained('gabarit_cours')->cascadeOnDelete();
            $table->text('contenu');
            $table->unsignedInteger('ordre')->default(0);
            $table->timestamps();
        });

        // ─── Types de projets du gabarit ─────────────────────────────────────
        Schema::create('gabarit_types_projets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gabarit_cours_id')->constrained('gabarit_cours')->cascadeOnDelete();
            $table->string('nom', 150);
            $table->text('description')->nullable();
            $table->decimal('ponderation', 5, 2)->nullable();
            $table->boolean('is_sommatif')->default(true);
            $table->boolean('generer_page_titre')->default(true);
            $table->boolean('generer_table_matieres')->default(true);
            $table->boolean('aide_reference')->default(false);
            $table->unsignedInteger('ordre')->default(0);
            $table->timestamps();
        });

        // ─── Sections des types de projets du gabarit ────────────────────────
        Schema::create('gabarit_types_projets_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gabarit_type_projet_id')->constrained('gabarit_types_projets')->cascadeOnDelete();
            $table->string('label');
            $table->string('type');                    // texte, paragraphes, individuel, entrevue…
            $table->unsignedInteger('ordre')->default(0);
            $table->timestamps();
        });

        // ─── Étapes d'échéancier du gabarit ──────────────────────────────────
        Schema::create('gabarit_echeancier_etapes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gabarit_cours_id')->constrained('gabarit_cours')->cascadeOnDelete();
            $table->unsignedTinyInteger('semaine');
            $table->text('etape');
            $table->unsignedTinyInteger('ordre')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gabarit_echeancier_etapes');
        Schema::dropIfExists('gabarit_types_projets_sections');
        Schema::dropIfExists('gabarit_types_projets');
        Schema::dropIfExists('gabarit_cours_objectifs');
        Schema::dropIfExists('gabarit_cours');
    }
};
