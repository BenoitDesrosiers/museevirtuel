<?php

namespace App\Enums;

enum TypeSection: string
{
    case Texte = 'texte';
    case Paragraphes = 'paragraphes';
    case Individuel = 'individuel';
    case Entrevue = 'entrevue';
    case Video = 'video';
    case Audio = 'audio';
    case ChoixQuestions = 'choix_questions';
    case Tache = 'tache';
    case SchemaVisuel = 'schema_visuel';

    /**
     * Retourne le libellé français du type de section.
     */
    public function label(): string
    {
        return match ($this) {
            self::Texte => 'Texte',
            self::Paragraphes => 'Paragraphes',
            self::Individuel => 'Individuel',
            self::Entrevue => 'Schéma d\'entrevue',
            self::Video => 'Vidéo',
            self::Audio => 'Audio',
            self::ChoixQuestions => 'Choix de questions',
            self::Tache => 'Tâches',
            self::SchemaVisuel => 'Schéma visuel',
        };
    }
}
