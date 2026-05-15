<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crée les tables pour l'intégration Zotero des étudiants.
 *
 * etudiant_zotero_credentials  → clé API Zotero chiffrée par étudiant
 * etudiant_references          → références personnelles de l'étudiant (manuelles ou sync Zotero)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─── Credentials Zotero de l'étudiant ─────────────────────────────────
        Schema::create('etudiant_zotero_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            // L'identifiant numérique du compte Zotero (visible dans les paramètres API)
            $table->string('zotero_user_id', 50);
            // La clé API stockée chiffrée — jamais en clair
            $table->text('api_key');
            $table->timestamp('synchronise_le')->nullable();
            $table->timestamps();
        });

        // ─── Références personnelles de l'étudiant ────────────────────────────
        Schema::create('etudiant_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Clé unique Zotero (ex. "ABC12DEF") — null si référence ajoutée manuellement
            $table->string('zotero_item_key', 50)->nullable();
            $table->string('titre', 500);
            // Auteurs sérialisés en JSON (ex. [{"prenom": "Marie", "nom": "Curie"}])
            $table->text('auteurs')->nullable();
            $table->smallInteger('annee')->nullable();
            // Type Zotero : journalArticle, book, bookSection, webpage, thesis…
            $table->string('type_source', 100)->nullable();
            $table->string('url', 500)->nullable();
            $table->string('doi', 255)->nullable();
            // Nom de la revue, de l'éditeur ou de la conférence
            $table->string('publication', 255)->nullable();
            $table->unsignedInteger('ordre')->default(0);
            $table->timestamps();

            // Empêche les doublons lors de synchronisations répétées
            $table->unique(['user_id', 'zotero_item_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('etudiant_references');
        Schema::dropIfExists('etudiant_zotero_credentials');
    }
};
