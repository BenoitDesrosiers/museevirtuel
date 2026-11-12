<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateEtudiantAction
{
    /**
     * Trouve ou crée un étudiant à partir de son numéro de DA.
     *
     * Si le DA existe déjà, retourne l'étudiant existant.
     * Sinon, crée un nouveau compte avec un email unique généré.
     *
     * @param  string  $noDa  Numéro de DA de l'étudiant
     * @param  string  $prenom  Prénom
     * @param  string  $nom  Nom de famille
     * @param  string|null  $email  Email fourni manuellement (optionnel)
     */
    public function execute(string $noDa, string $prenom, string $nom, ?string $email = null): User
    {
        // Le DA est l'identifiant fiable — si l'étudiant existe déjà, on le retourne
        $existant = User::where('no_da', $noDa)->where('role', 'etudiant')->first();
        if ($existant) {
            return $existant;
        }

        $email ??= $this->generateUniqueEmail($prenom, $nom);

        // create() et non firstOrCreate() : retrouver un compte par courriel pourrait récupérer celui
        // d'un enseignant ou d'un autre étudiant. Le courriel est libre (généré unique, ou validé
        // par StoreClasseEtudiantRequest) ; sinon la contrainte unique fait échouer la transaction.
        return User::create([
            'email' => strtolower($email),
            'prenom' => $prenom,
            'nom' => $nom,
            'no_da' => $noDa,
            // Hash::make() explicite — même si le cast 'hashed' le ferait,
            // on garde l'intention lisible et indépendante du modèle.
            'password' => Hash::make('password'),
            'role' => 'etudiant',
            'email_verified_at' => now(),
        ]);
    }

    /**
     * Génère un email unique à partir du prénom et du nom.
     *
     * En cas de collision (deux Marie Tremblay), ajoute un suffixe numérique :
     * marie.tremblay@etu.cegepdrummond.ca → marie.tremblay2@etu.cegepdrummond.ca
     */
    private function generateUniqueEmail(string $prenom, string $nom): string
    {
        $prenomClean = Str::lower(Str::ascii($prenom));
        $nomClean = Str::lower(Str::ascii($nom));
        $base = "{$prenomClean}.{$nomClean}@etu.cegepdrummond.ca";

        if (! User::where('email', $base)->exists()) {
            return $base;
        }

        $suffix = 2;
        do {
            $candidate = "{$prenomClean}.{$nomClean}{$suffix}@etu.cegepdrummond.ca";
            $suffix++;
        } while (User::where('email', $candidate)->exists());

        return $candidate;
    }
}
