<?php

namespace Database\Factories;

use App\Models\Thematique;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Thematique>
 */
class ThematiqueFactory extends Factory
{
    protected $model = Thematique::class;

    public function definition(): array
    {
        return [
            'nom' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'periode_historique' => fake()->words(2, true),
            'enseignant_id' => User::factory()->state(['role' => 'enseignant']),
        ];
    }
}
