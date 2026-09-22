<?php

use App\Models\Etablissement;
use App\Models\Thematique;
use App\Models\User;
use Database\Seeders\EnseignantsTestSeeder;
use Database\Seeders\EtablissementSeeder;
use Database\Seeders\ThematiqueDemoSeeder;
use Illuminate\Support\Facades\Hash;

test('it creates the named test teacher accounts', function () {
    $this->seed(EnseignantsTestSeeder::class);
    $this->seed(EnseignantsTestSeeder::class);
    $this->seed(EtablissementSeeder::class);
    $this->seed(ThematiqueDemoSeeder::class);

    $enseignants = User::query()
        ->whereIn('email', [
            'maryse.perron@demo.com',
            'michel.landry@demo.com',
            'pierre-olivier.fontaine@demo.com',
        ])
        ->get()
        ->keyBy('email');
    $etablissement = Etablissement::query()
        ->where('code', 'CEGEP-DEMO')
        ->sole();

    expect($enseignants)->toHaveCount(3)
        ->and($enseignants['maryse.perron@demo.com']->name)->toBe('Maryse Perron')
        ->and($enseignants['michel.landry@demo.com']->name)->toBe('Michel Landry')
        ->and($enseignants['pierre-olivier.fontaine@demo.com']->name)->toBe('Pierre-Olivier Fontaine')
        ->and($enseignants->every(fn (User $enseignant): bool => $enseignant->role === 'enseignant'
            && $enseignant->statut === 'actif'
            && $enseignant->etablissement_id === $etablissement->id
            && Hash::check('password', $enseignant->password),
        ))->toBeTrue()
        ->and(Thematique::parEtablissement($etablissement->id)->count())->toBe(8);
});
