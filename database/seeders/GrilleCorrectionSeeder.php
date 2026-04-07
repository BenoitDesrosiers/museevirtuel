<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class GrilleCorrectionSeeder extends Seeder
{
    /**
     * Ce seeder est désactivé depuis Sprint 5.
     * La logique de création de grille est désormais inlinée dans DemoSeeder,
     * liée à un TypeProjet plutôt qu'à une Classe.
     */
    public function run(): void
    {
        $this->command->warn('GrilleCorrectionSeeder est désactivé. La grille est créée via DemoSeeder (TypeProjet).');
    }
}
