<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'prenom' => 'Admin',
                'nom' => 'Système',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );

        $this->call(EpoquesHistoriquesSeeder::class);
        $this->call(RegionsAdministrativesSeeder::class);
        $this->call(ThematiquesGlobalesCegepSeeder::class);
        $this->call(GabaritCoursCompletSeeder::class);
        $this->call(GabaritCoursComplementaireSeeder::class);
        $this->call(GabaritDepSeeder::class);
        $this->call(DemoSeeder::class);
        $this->call(MuseeDemoSeeder::class);
        $this->call(DepDemoSeeder::class);
        $this->call(CoursComplementaireDemoSeeder::class);
        $this->call(EnseignantsTestSeeder::class);
        $this->call(EcheancierEtapesSeeder::class);
        $this->call(EtablissementSeeder::class);
        $this->call(ThematiqueDemoSeeder::class);
        $this->call(TemoinSeeder::class);
    }
}
