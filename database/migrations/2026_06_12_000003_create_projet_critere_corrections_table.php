<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crée la table des corrections de critères appliquées par l'enseignant.
 *
 * Une correction lie un critère à un projet de recherche, avec optionnellement
 * un étudiant ciblé. Quand user_id est null, la correction s'applique à tous
 * les membres du groupe. Une correction spécifique à un étudiant (user_id non null)
 * prend le dessus sur la correction de groupe pour cet étudiant.
 *
 * Note sur l'unicité : MySQL et SQLite traitent deux valeurs NULL comme distinctes
 * dans une contrainte UNIQUE, ce qui empêcherait d'imposer l'unicité de la correction
 * « groupe » (user_id = null) au niveau de la base de données.
 * L'unicité est donc assurée par le contrôleur via `updateOrCreate`.
 *
 * La colonne source_id permet de tracer la duplication d'une correction :
 * quand le professeur duplique une correction pour donner des points différents
 * à deux étudiants, le clone pointe vers la correction d'origine via source_id.
 */
return new class extends Migration
{
    /**
     * Crée la table projet_critere_corrections avec FK auto-référentielle.
     */
    public function up(): void
    {
        // Étape 1 : créer la table sans la FK auto-référentielle (source_id).
        Schema::create('projet_critere_corrections', function (Blueprint $table) {
            $table->id();

            $table->foreignId('projet_id')
                ->constrained('projets_recherche')
                ->cascadeOnDelete();

            $table->foreignId('critere_id')
                ->constrained('type_projet_criteres')
                ->cascadeOnDelete();

            // Étudiant ciblé : null = tous les membres du groupe.
            // Note : ce n'est PAS l'auteur de la correction (toujours l'enseignant),
            // mais la personne À QUI cette correction s'applique.
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnDelete();

            // Points réellement attribués ou déduits.
            // Pour positif : entre 0 et critere.pointage.
            // Pour négatif : le montant de la déduction (positif, ex. 2.00 = -2 pts).
            $table->decimal('points', 6, 2)->nullable();

            // Commentaire optionnel de l'enseignant sur cette correction.
            $table->text('commentaire')->nullable();

            // Pour les critères positifs : true = crochet vert, tous les points accordés.
            // Quand verifie = true et points est null, on utilise critere.pointage.
            $table->boolean('verifie')->default(false);

            // Référence à la correction d'origine si ce record est un clone.
            // Stocké comme bigint non signé pour permettre l'ajout de la FK après création.
            $table->unsignedBigInteger('source_id')->nullable();

            $table->timestamps();

            // Index pour les requêtes fréquentes (pas de UNIQUE : voir note sur les NULL).
            $table->index(['projet_id', 'critere_id', 'user_id'], 'corrections_projet_critere_user_idx');
        });

        // Étape 2 : ajouter la FK auto-référentielle maintenant que la table existe.
        Schema::table('projet_critere_corrections', function (Blueprint $table) {
            $table->foreign('source_id')
                ->references('id')
                ->on('projet_critere_corrections')
                ->nullOnDelete();
        });
    }

    /**
     * Supprime la table projet_critere_corrections.
     */
    public function down(): void
    {
        // Retirer la FK auto-référentielle avant de supprimer la table.
        Schema::table('projet_critere_corrections', function (Blueprint $table) {
            $table->dropForeign(['source_id']);
        });

        Schema::dropIfExists('projet_critere_corrections');
    }
};
