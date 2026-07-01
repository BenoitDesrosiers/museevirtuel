<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

class ImportTranscriptionService
{
    /**
     * Détecte le format du fichier importé selon son extension.
     *
     * @return 'txt'|'srt'|'vtt'
     */
    public function detecterFormat(UploadedFile $fichier): string
    {
        $extension = strtolower($fichier->getClientOriginalExtension());

        return match ($extension) {
            'srt' => 'srt',
            'vtt' => 'vtt',
            default => 'txt',
        };
    }

    /**
     * Importe une transcription depuis un fichier uploadé.
     *
     * Retourne un tableau avec :
     * - transcription : texte brut complet
     * - segments      : tableau de {start, end, text} ou null si aucun horodatage
     *
     * @return array{transcription: string, segments: list<array{start: float, end: float, text: string}>|null}
     */
    public function importer(UploadedFile $fichier): array
    {
        // Lecture avec détection d'encodage pour supporter latin-1 et UTF-8.
        $contenu = file_get_contents($fichier->getRealPath());
        $encoding = mb_detect_encoding($contenu, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);

        if ($encoding && $encoding !== 'UTF-8') {
            $contenu = mb_convert_encoding($contenu, 'UTF-8', $encoding);
        }

        $format = $this->detecterFormat($fichier);

        return match ($format) {
            'srt' => $this->fromSrt($contenu),
            'vtt' => $this->fromVtt($contenu),
            default => $this->fromTexte($contenu),
        };
    }

    /**
     * Parse un texte brut — aucun horodatage, transcription complète seulement.
     *
     * @return array{transcription: string, segments: null}
     */
    public function fromTexte(string $contenu): array
    {
        return [
            'transcription' => trim($contenu),
            'segments' => null,
        ];
    }

    /**
     * Parse le format SubRip (.srt).
     *
     * Format attendu :
     *   1
     *   00:00:01,000 --> 00:00:04,500
     *   Texte du premier segment.
     *
     *   2
     *   00:00:05,000 --> 00:00:08,000
     *   Texte du second segment.
     *
     * @return array{transcription: string, segments: list<array{start: float, end: float, text: string}>}
     */
    public function fromSrt(string $contenu): array
    {
        $segments = [];
        // Découpe par bloc séparé d'une ou plusieurs lignes vides.
        $blocs = preg_split('/\n{2,}/', trim($contenu));

        foreach ($blocs as $bloc) {
            $lignes = explode("\n", trim($bloc));

            // Un bloc SRT valide = au minimum 3 lignes (numéro, timestamp, texte).
            if (count($lignes) < 3) {
                continue;
            }

            // Ligne 2 : timestamp — on ignore la ligne 1 (numéro de séquence).
            $timestamps = $this->parseSrtTimestamp($lignes[1]);
            if ($timestamps === null) {
                continue;
            }

            // Le texte est tout ce qui suit la ligne de timestamp.
            $texte = implode(' ', array_slice($lignes, 2));

            $segments[] = [
                'start' => $timestamps['start'],
                'end' => $timestamps['end'],
                'text' => $this->nettoyerTexte($texte),
            ];
        }

        return $this->buildResultat($segments);
    }

    /**
     * Parse le format WebVTT (.vtt).
     *
     * Format attendu :
     *   WEBVTT
     *
     *   00:00:01.000 --> 00:00:04.500
     *   Texte du premier segment.
     *
     * @return array{transcription: string, segments: list<array{start: float, end: float, text: string}>}
     */
    public function fromVtt(string $contenu): array
    {
        $segments = [];
        $blocs = preg_split('/\n{2,}/', trim($contenu));

        foreach ($blocs as $bloc) {
            $lignes = explode("\n", trim($bloc));

            if (count($lignes) < 2) {
                continue;
            }

            // Cherche la ligne de timestamp (contient " --> ").
            $idxTimestamp = null;
            foreach ($lignes as $idx => $ligne) {
                if (str_contains($ligne, ' --> ')) {
                    $idxTimestamp = $idx;
                    break;
                }
            }

            if ($idxTimestamp === null) {
                continue;
            }

            $timestamps = $this->parseVttTimestamp($lignes[$idxTimestamp]);
            if ($timestamps === null) {
                continue;
            }

            $texte = implode(' ', array_slice($lignes, $idxTimestamp + 1));

            if (trim($texte) === '') {
                continue;
            }

            $segments[] = [
                'start' => $timestamps['start'],
                'end' => $timestamps['end'],
                'text' => $this->nettoyerTexte($texte),
            ];
        }

        return $this->buildResultat($segments);
    }

    /**
     * Construit le tableau de retour normalisé à partir d'une liste de segments.
     *
     * @param  list<array{start: float, end: float, text: string}>  $segments
     * @return array{transcription: string, segments: list<array{start: float, end: float, text: string}>}
     */
    private function buildResultat(array $segments): array
    {
        return [
            'transcription' => implode(' ', array_column($segments, 'text')),
            'segments' => $segments,
        ];
    }

    /**
     * Parse un timestamp SRT (ex. "00:01:23,456 --> 00:01:28,000").
     * Les millisecondes sont séparées par une virgule en SRT.
     *
     * @return array{start: float, end: float}|null
     */
    private function parseSrtTimestamp(string $ligne): ?array
    {
        // Format : HH:MM:SS,mmm --> HH:MM:SS,mmm
        $pattern = '/^(\d{2}):(\d{2}):(\d{2}),(\d{3})\s*-->\s*(\d{2}):(\d{2}):(\d{2}),(\d{3})$/';

        if (! preg_match($pattern, trim($ligne), $m)) {
            return null;
        }

        return [
            'start' => $this->toSeconds((int) $m[1], (int) $m[2], (int) $m[3], (int) $m[4]),
            'end' => $this->toSeconds((int) $m[5], (int) $m[6], (int) $m[7], (int) $m[8]),
        ];
    }

    /**
     * Parse un timestamp VTT (ex. "00:01:23.456 --> 00:01:28.000 position:50%").
     * Les millisecondes sont séparées par un point en VTT.
     * Des métadonnées (position, align) peuvent suivre après un espace.
     *
     * @return array{start: float, end: float}|null
     */
    private function parseVttTimestamp(string $ligne): ?array
    {
        // Format : [HH:]MM:SS.mmm --> [HH:]MM:SS.mmm [metadata...]
        $pattern = '/^(?:(\d{2}):)?(\d{2}):(\d{2})\.(\d{3})\s*-->\s*(?:(\d{2}):)?(\d{2}):(\d{2})\.(\d{3})/';

        if (! preg_match($pattern, trim($ligne), $m)) {
            return null;
        }

        return [
            'start' => $this->toSeconds((int) ($m[1] ?? 0), (int) $m[2], (int) $m[3], (int) $m[4]),
            'end' => $this->toSeconds((int) ($m[5] ?? 0), (int) $m[6], (int) $m[7], (int) $m[8]),
        ];
    }

    /**
     * Convertit des composants HH/MM/SS/ms en secondes décimales.
     */
    private function toSeconds(int $h, int $m, int $s, int $ms): float
    {
        return $h * 3600 + $m * 60 + $s + $ms / 1000;
    }

    /**
     * Supprime les balises HTML/XML et les espaces superflus d'un texte de sous-titre.
     */
    private function nettoyerTexte(string $texte): string
    {
        return trim(strip_tags($texte));
    }
}
