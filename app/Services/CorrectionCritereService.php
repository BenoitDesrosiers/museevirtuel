<?php

namespace App\Services;

use App\Exceptions\TransactionEchoueeException;
use App\Models\ProjetCritereCorrection;
use App\Models\ProjetRecherche;
use App\Models\TypeProjetCritere;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Corrections des critères d'évaluation d'un projet : enregistrement, suppression et clonage.
 */
class CorrectionCritereService
{
    /**
     * Crée ou met à jour la correction d'un critère.
     *
     * @param  array{user_id?: int|null, points?: float|int|null, commentaire?: string|null}  $donnees
     * @return array{correction: ProjetCritereCorrection, idsRetires: list<int>}
     *
     * @throws Throwable
     */
    public function enregistrer(ProjetRecherche $projet, TypeProjetCritere $critere, array $donnees, bool $verifie): array
    {
        try {
            $resultat = DB::transaction(function () use ($projet, $critere, $donnees, $verifie) {
                $idsRetires = [];

                if (! isset($donnees['user_id'])) {
                    $individuelles = ProjetCritereCorrection::where('projet_id', $projet->id)
                        ->where('critere_id', $critere->id)
                        ->whereNotNull('user_id');

                    $idsRetires = $individuelles->pluck('user_id')->all();

                    if ($idsRetires !== []) {
                        $individuelles->delete();
                    }
                }

                $correction = ProjetCritereCorrection::updateOrCreate(
                    [
                        'projet_id' => $projet->id,
                        'critere_id' => $critere->id,
                        'user_id' => $donnees['user_id'] ?? null,
                    ],
                    [
                        'points' => $donnees['points'] ?? null,
                        'commentaire' => $donnees['commentaire'] ?? null,
                        'verifie' => $verifie,
                    ]
                );

                return ['correction' => $correction, 'idsRetires' => $idsRetires];
            });
        } catch (QueryException $e) {
            report($e);

            throw TransactionEchoueeException::depuis($e);
        }

        return $resultat;
    }

    /**
     * Supprime une correction et tous ses clones.
     *
     * @throws TransactionEchoueeException
     */
    public function supprimer(ProjetCritereCorrection $correction): void
    {
        try {
            DB::transaction(function () use ($correction) {
                $correction->clones()->delete();
                $correction->delete();
            });
        } catch (QueryException $e) {
            report($e);

            throw TransactionEchoueeException::depuis($e);
        }
    }

    /**
     * Clone une correction pour un étudiant, en remplaçant sa correction individuelle existante.
     *
     * @param  array{user_id: int, points?: float|int|null, commentaire?: string|null}  $donnees
     *
     * @throws TransactionEchoueeException
     */
    public function cloner(ProjetRecherche $projet, ProjetCritereCorrection $source, array $donnees, bool $verifie): ProjetCritereCorrection
    {
        try {
            $clone = DB::transaction(function () use ($projet, $source, $donnees, $verifie) {
                ProjetCritereCorrection::where('projet_id', $projet->id)
                    ->where('critere_id', $source->critere_id)
                    ->where('user_id', $donnees['user_id'])
                    ->delete();

                return ProjetCritereCorrection::create([
                    'projet_id' => $projet->id,
                    'critere_id' => $source->critere_id,
                    'user_id' => $donnees['user_id'],
                    'points' => $donnees['points'] ?? null,
                    'commentaire' => $donnees['commentaire'] ?? null,
                    'verifie' => $verifie,
                    'source_id' => $source->id,
                ]);
            });
        } catch (QueryException $e) {
            report($e);

            throw TransactionEchoueeException::depuis($e);
        }

        return $clone;
    }
}
