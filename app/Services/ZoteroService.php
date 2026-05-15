<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class ZoteroService
{
    private const API_BASE = 'https://api.zotero.org';

    private const API_VERSION = '3';

    /**
     * Vérifie qu'une clé API Zotero est valide en récupérant les infos du compte.
     *
     * Retourne true si la clé est acceptée, false sinon.
     *
     * @throws ConnectionException Si l'API Zotero est inaccessible
     */
    public function validateApiKey(string $zoteroUserId, string $apiKey): bool
    {
        $response = Http::withHeaders($this->headers())
            ->timeout(10)
            ->get(self::API_BASE."/users/{$zoteroUserId}/items", [
                'key' => $apiKey,
                'limit' => 1,
                'format' => 'json',
            ]);

        return $response->successful();
    }

    /**
     * Récupère les items de la bibliothèque personnelle Zotero de l'étudiant.
     *
     * Retourne un tableau d'items transformés et prêts à insérer en base.
     * La limite par défaut est 100 items — suffisant pour un usage étudiant courant.
     *
     * @param  string  $zoteroUserId  Identifiant numérique du compte Zotero
     * @param  string  $apiKey  Clé API chiffrée (déchiffrée avant appel)
     * @param  int  $limit  Nombre maximum d'items à récupérer
     * @return array<int, array<string, mixed>>
     *
     * @throws ConnectionException Si l'API Zotero est inaccessible
     * @throws RequestException Si l'API retourne une erreur HTTP
     */
    public function fetchItems(string $zoteroUserId, string $apiKey, int $limit = 100): array
    {
        $response = Http::withHeaders($this->headers())
            ->timeout(30)
            ->get(self::API_BASE."/users/{$zoteroUserId}/items", [
                'key' => $apiKey,
                'limit' => $limit,
                'format' => 'json',
                'sort' => 'dateAdded',
                'direction' => 'desc',
            ]);

        $response->throw();

        return collect($response->json())
            ->filter(fn (array $item): bool => isset($item['data']['title']) && $item['data']['title'] !== '')
            ->map(fn (array $item): array => $this->parseItem($item))
            ->values()
            ->all();
    }

    /**
     * Transforme un item brut de l'API Zotero en tableau normalisé pour la base.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    public function parseItem(array $item): array
    {
        $data = $item['data'] ?? [];
        $key = $item['key'] ?? null;

        return [
            'zotero_item_key' => $key,
            'titre' => $data['title'] ?? '',
            'auteurs' => $this->parseAuteurs($data['creators'] ?? []),
            'annee' => $this->parseAnnee($data['date'] ?? ''),
            'type_source' => $data['itemType'] ?? null,
            'url' => $this->normaliseUrl($data['url'] ?? ''),
            'doi' => $data['DOI'] ?? null ?: null,
            // Champ selon le type : revue, éditeur, proceedings, etc.
            'publication' => $data['publicationTitle']
                ?? $data['publisher']
                ?? $data['proceedingsTitle']
                ?? $data['websiteTitle']
                ?? null,
        ];
    }

    /**
     * Retourne les en-têtes requis par l'API Zotero v3.
     *
     * @return array<string, string>
     */
    private function headers(): array
    {
        return ['Zotero-API-Version' => self::API_VERSION];
    }

    /**
     * Extrait les auteurs depuis le tableau `creators` de Zotero.
     *
     * Filtre sur les types pertinents (author, editor) et normalise les noms.
     *
     * @param  array<int, array<string, string>>  $creators
     * @return array<int, array<string, string>>|null
     */
    private function parseAuteurs(array $creators): ?array
    {
        $auteurs = collect($creators)
            ->filter(fn ($c) => in_array($c['creatorType'] ?? '', ['author', 'editor', 'translator']))
            ->map(function (array $creator): array {
                return [
                    'prenom' => $creator['firstName'] ?? '',
                    'nom' => $creator['lastName'] ?? ($creator['name'] ?? ''),
                ];
            })
            ->filter(fn (array $a): bool => $a['nom'] !== '')
            ->values()
            ->all();

        return ! empty($auteurs) ? $auteurs : null;
    }

    /**
     * Extrait l'année depuis une chaîne de date Zotero (formats variés : "2023", "2023-04", "April 2023"...).
     */
    private function parseAnnee(string $date): ?int
    {
        if ($date === '') {
            return null;
        }

        // Extraire le premier nombre de 4 chiffres trouvé dans la chaîne
        if (preg_match('/\b(\d{4})\b/', $date, $matches)) {
            $annee = (int) $matches[1];

            return ($annee >= 1000 && $annee <= 2100) ? $annee : null;
        }

        return null;
    }

    /**
     * Normalise une URL : retourne null si vide ou invalide.
     */
    private function normaliseUrl(string $url): ?string
    {
        $url = trim($url);

        return ($url !== '' && filter_var($url, FILTER_VALIDATE_URL)) ? $url : null;
    }
}
