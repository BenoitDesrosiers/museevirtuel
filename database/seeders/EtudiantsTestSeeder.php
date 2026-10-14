<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class EtudiantsTestSeeder extends Seeder
{
    /**
     * Crée test3 et l'inscrit dans les mêmes classes que test2.
     */
    public function run(): void
    {
        $etudiantSource = User::query()
            ->where('email', 'test2@test.com')
            ->firstOrFail();

        $classes = $etudiantSource->classesInscrites()->get();

        if ($classes->isEmpty()) {
            throw new \RuntimeException('test2@test.com doit être inscrit dans au moins une classe.');
        }

        $etudiant = User::updateOrCreate(
            ['email' => 'test3@test.com'],
            [
                'prenom' => 'test3',
                'nom' => 'test3',
                'no_da' => 'TEST3',
                'password' => Hash::make('password'),
                'role' => 'etudiant',
                'statut' => 'actif',
                'email_verified_at' => now(),
            ],
        );

        foreach ($classes as $classe) {
            $classe->etudiants()->syncWithoutDetaching([
                $etudiant->id => [
                    'no_da' => $etudiant->no_da,
                    'statut_cours' => 'Actif',
                ],
            ]);
        }
    }
}
