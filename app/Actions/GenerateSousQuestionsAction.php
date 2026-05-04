<?php

namespace App\Actions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Action de génération de sous-questions via IA.
 *
 * Architecture prévue pour Sprint 21+. Retourne 501 si aucune clé API n'est configurée.
 * Brancher ici l'appel OpenAI/Anthropic quand la clé sera disponible.
 */
class GenerateSousQuestionsAction
{
    /**
     * Génère des sous-questions à partir d'une question principale.
     *
     * @param  int  $count  Nombre de sous-questions à générer (entre 6 et 8)
     * @return array<int, string>
     *
     * @throws HttpException Si aucune clé API n'est configurée
     */
    public function execute(string $questionPrincipale, int $count = 7): array
    {
        // Aucune clé API configurée — fonctionnalité IA non disponible
        abort_if(
            empty(config('services.openai.key')),
            501,
            __('taches.ia_non_disponible')
        );

        // TODO: brancher l'appel IA ici (OpenAI chat completions ou équivalent)
        return [];
    }
}
