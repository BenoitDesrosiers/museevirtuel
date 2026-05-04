<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crée la table des commentaires d'enseignant sur les renvois (endnotes).
 *
 * Chaque commentaire est lié à un renvoi spécifique du projet et à l'enseignant
 * qui l'a rédigé. La suppression en cascade garantit qu'un commentaire orphelin
 * ne persiste pas si le renvoi ou l'utilisateur est supprimé.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projet_renvoi_commentaires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('renvoi_id')
                ->constrained('projet_renvois')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->text('contenu');
            $table->timestamps();

            $table->index('renvoi_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projet_renvoi_commentaires');
    }
};
