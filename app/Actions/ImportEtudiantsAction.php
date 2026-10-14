<?php

namespace App\Actions;

use App\Enums\StatutEtudiantCours;
use App\Models\Classe;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportEtudiantsAction
{
    public function __construct(private readonly CreateEtudiantAction $createEtudiant) {}

    /**
     * Importe les étudiants d'un fichier CSV dans une classe.
     *
     * - Détecte et convertit l'encodage (Excel → UTF-8)
     * - Précharge les IDs inscrits pour éviter le N+1
     * - Enveloppe toutes les écritures dans une transaction DB
     *
     * @param  Classe  $classe  La classe cible
     * @param  string  $csvContent  Contenu brut du fichier CSV
     * @return int Nombre d'étudiants nouvellement ajoutés
     */
    public function execute(Classe $classe, string $csvContent): int
    {
        $csvContent = $this->normalizeEncoding($csvContent);

        // Lire toutes les lignes d'abord — fermer le fichier temporaire avant la transaction
        $rows = $this->parseCsvRows($csvContent);

        // Précharger les IDs déjà inscrits (O(1) pour les vérifications dans la boucle)
        $existingUserIds = $classe->etudiants()->allRelatedIds()->flip()->toArray();

        $created = 0;

        DB::transaction(function () use ($classe, $rows, &$existingUserIds, &$created) {
            foreach ($rows as ['noDa' => $noDa, 'prenom' => $prenom, 'nom' => $nom, 'statut' => $statut]) {
                $etudiant = $this->createEtudiant->execute($noDa, $prenom, $nom);

                // Un étudiant peut être inscrit à plusieurs classes, mais une seule fois dans celle-ci.
                if (! isset($existingUserIds[$etudiant->id])) {
                    $classe->etudiants()->attach($etudiant->id, [
                        'no_da' => $noDa,
                        'statut_cours' => StatutEtudiantCours::tryFrom(Str::lower($statut))?->value
                            ?? StatutEtudiantCours::Actif->value,
                    ]);
                    // Marquer comme inscrit pour éviter un double attach si le DA apparaît deux fois dans le CSV
                    $existingUserIds[$etudiant->id] = true;
                    $created++;
                }
            }
        });

        return $created;
    }

    /**
     * Convertit le contenu en UTF-8 si nécessaire (ex. : export Excel Windows-1252).
     */
    private function normalizeEncoding(string $content): string
    {
        $encoding = mb_detect_encoding($content, ['UTF-8', 'Windows-1252', 'ISO-8859-1'], true);

        if ($encoding && $encoding !== 'UTF-8') {
            return mb_convert_encoding($content, 'UTF-8', $encoding);
        }

        return $content;
    }

    /**
     * Parse le contenu CSV et retourne les lignes valides sous forme de tableau associatif.
     *
     * @return array<int, array{noDa: string, prenom: string, nom: string, statut: string}>
     */
    private function parseCsvRows(string $content): array
    {
        $tmp = tmpfile();
        fwrite($tmp, $content);
        rewind($tmp);

        $rows = [];

        while (($row = fgetcsv($tmp, 0, ';')) !== false) {
            if (count($row) < 4) {
                continue;
            }

            [$noDa, $nom, $prenom, $statut] = $row;

            // Retirer le BOM UTF-8 éventuel sur le premier champ
            $noDa = ltrim(trim($noDa), "\xEF\xBB\xBF");

            if ($this->isHeaderRow($noDa, $nom, $prenom)) {
                continue;
            }

            $nom = trim($nom);
            $prenom = trim($prenom);
            $statut = trim($statut);

            if (empty($noDa) || empty($nom) || empty($prenom)) {
                continue;
            }

            $rows[] = compact('noDa', 'nom', 'prenom', 'statut');
        }

        fclose($tmp);

        return $rows;
    }

    /**
     * Détermine si une ligne contient les intitulés de colonnes plutôt qu'un étudiant.
     */
    private function isHeaderRow(string $noDa, string $nom, string $prenom): bool
    {
        $normalize = fn (string $value): string => preg_replace(
            '/[^a-z0-9]/',
            '',
            mb_strtolower(Str::ascii($value)),
        ) ?? '';

        $noDa = $normalize($noDa);
        $nom = $normalize($nom);
        $prenom = $normalize($prenom);

        return str_contains($noDa, 'no')
            && str_contains($noDa, 'da')
            && str_contains($nom, 'nom')
            && str_contains($prenom, 'prenom');
    }
}
