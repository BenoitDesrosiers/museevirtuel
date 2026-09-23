<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class EnseignantsTestSeeder extends Seeder
{
    /**
     * Crée ou met à jour les comptes enseignants destinés aux tests manuels.
     */
    public function run(): void
    {
        foreach ([
            ['prenom' => 'Maryse', 'nom' => 'Perron', 'email' => 'maryse.perron@demo.com'],
            ['prenom' => 'Michel', 'nom' => 'Landry', 'email' => 'michel.landry@demo.com'],
            ['prenom' => 'Pierre-Olivier', 'nom' => 'Fontaine', 'email' => 'pierre-olivier.fontaine@demo.com'],
        ] as $enseignant) {
            User::updateOrCreate(
                ['email' => $enseignant['email']],
                [
                    ...$enseignant,
                    'email_verified_at' => now(),
                    'password' => Hash::make('password'),
                    'role' => 'enseignant',
                    'statut' => 'actif',
                ],
            );
        }
    }
}
