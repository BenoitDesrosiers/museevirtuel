<?php

namespace Database\Seeders;

use App\Models\Etablissement;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class EtablissementSeeder extends Seeder
{
    public function run(): void
    {
        // Supprimer tout établissement parasite (anciens seeds)
        Etablissement::whereNotIn('code', ['CEGEP-DEMO'])->delete();

        /** @var Etablissement $cegep */
        $cegep = Etablissement::updateOrCreate(
            ['code' => 'CEGEP-DEMO'],
            [
                'nom' => 'Cégep Demo',
                'ville' => 'Montréal',
                'code' => 'CEGEP-DEMO',
            ]
        );

        // Assigner l'enseignant démo existant au cégep
        User::where('email', 'prof@demo.com')
            ->update(['etablissement_id' => $cegep->id]);

        // Créer prof2 s'il n'existe pas encore
        User::updateOrCreate(
            ['email' => 'prof2@demo.com'],
            [
                'prenom' => 'Marie',
                'nom' => 'Lapointe',
                'password' => Hash::make('password'),
                'role' => 'enseignant',
                'email_verified_at' => now(),
                'etablissement_id' => $cegep->id,
            ]
        );

        // Créer prof3 s'il n'existe pas encore
        User::updateOrCreate(
            ['email' => 'prof3@demo.com'],
            [
                'prenom' => 'Jean',
                'nom' => 'Tremblay',
                'password' => Hash::make('password'),
                'role' => 'enseignant',
                'email_verified_at' => now(),
                'etablissement_id' => $cegep->id,
            ]
        );

        // Assigner les témoins démo au cégep
        User::whereIn('email', array_map(
            fn ($i) => "temoin{$i}@demo.com",
            range(1, 10)
        ))->update(['etablissement_id' => $cegep->id]);

        // Assigner les thématiques de l'enseignant démo à ce cégep
        $prof = User::where('email', 'prof@demo.com')->first();
        if ($prof) {
            $prof->thematiques()->update(['etablissement_id' => $cegep->id]);
        }
    }
}
