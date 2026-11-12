<?php

namespace App\Actions;

use App\Exceptions\TransactionEchoueeException;
use App\Models\Classe;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Inscrit manuellement un étudiant dans une classe.
 */
readonly class InscrireEtudiantClasseAction
{
    public function __construct(private CreateEtudiantAction $createEtudiantAction) {}

    /**
     * Crée (ou retrouve par DA) l'étudiant et l'inscrit dans la classe.
     *
     * La création du compte et l'inscription sont faites ensemble : si l'inscription échoue,
     * aucun compte orphelin ne reste en base.
     *
     * @param  array{no_da: int|string, prenom: string, nom: string, email?: string|null, statut_cours: string}  $donnees
     *
     * @throws TransactionEchoueeException
     */
    public function execute(Classe $classe, array $donnees): User
    {
        try {
            $etudiant = DB::transaction(function () use ($classe, $donnees) {
                $etudiant = $this->createEtudiantAction->execute(
                    (string) $donnees['no_da'],
                    $donnees['prenom'],
                    $donnees['nom'],
                    $donnees['email'] ?? null,
                );

                $classe->etudiants()->attach($etudiant->id, [
                    'no_da' => $donnees['no_da'],
                    'statut_cours' => $donnees['statut_cours'],
                ]);

                return $etudiant;
            });
        } catch (QueryException $e) {
            report($e);

            throw TransactionEchoueeException::depuis($e);
        }

        return $etudiant;
    }
}
