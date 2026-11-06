<?php

namespace App\Actions;

use App\Exceptions\TransactionEchoueeException;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Action qui crée le compte d'un témoin (personne âgée) à la fin de l'inscription en deux étapes.
 */
class InscrireTemoin
{
    /**
     * Crée le compte avec ses thématiques et ses établissements choisis.
     *
     * @param  array<string, mixed>  $donnees  Données validées de l'étape 1 (identité, mot de passe, choix par établissement)
     * @param  string  $signature  Signature électronique des engagements (étape 2)
     *
     * @throws TransactionEchoueeException
     */
    public function execute(array $donnees, string $signature): User
    {
        try {
            $user = DB::transaction(function () use ($donnees, $signature) {
                $nouveau = User::create([
                    'prenom' => $donnees['prenom'],
                    'nom' => $donnees['nom'],
                    'email' => $donnees['email'],
                    'password' => $donnees['password'],
                    'role' => 'personne_agee',
                    'statut' => 'en_attente',
                    'description' => $donnees['description'],
                    'disponible_appel_distance' => $donnees['disponible_appel_distance'] ?? false,
                    'engagements_acceptes_le' => now(),
                    'signature_electronique' => $signature,
                ]);

                $thematiqueIds = collect($donnees['choix'])
                    ->flatMap(fn ($c) => $c['thematique_ids'] ?? [])
                    ->unique()
                    ->all();

                if ($thematiqueIds !== []) {
                    $nouveau->thematiquesChoisies()->sync($thematiqueIds);
                }

                $etablissements = collect($donnees['choix'])->mapWithKeys(fn ($c) => [
                    $c['etablissement_id'] => ['theme_libre' => $c['theme_libre'] ?? null],
                ])->all();

                $nouveau->etablissementsChoisis()->sync($etablissements);

                return $nouveau;
            });
        } catch (QueryException $e) {
            report($e);

            throw TransactionEchoueeException::depuis($e);
        }

        return $user;
    }
}
