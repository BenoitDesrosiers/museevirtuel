<?php

use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\GroupeVideo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

// ─── Helpers partagés — Vidéos de groupe ──────────────────────────────────────

/**
 * Crée un scénario standard : enseignant, cours, classe, groupe, étudiant membre.
 *
 * @return array{enseignant: User, etudiant: User, cours: Cours, classe: Classe, groupe: Groupe}
 */
function creerScenarioVideo(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $etudiant = User::factory()->create(['role' => 'etudiant']);

    $cours = Cours::create([
        'nom_cours' => 'Histoire du Québec',
        'description' => 'Test',
        'code' => '330-VID',
        'groupe' => 'A',
        'enseignant_id' => $enseignant->id,
    ]);

    $classe = Classe::create(['cours_id' => $cours->id]);
    $classe->etudiants()->attach($etudiant->id);

    $groupe = Groupe::create([
        'classe_id' => $classe->id,
        'created_by' => $etudiant->id,
    ]);
    $groupe->membres()->attach($etudiant->id);

    return compact('enseignant', 'etudiant', 'cours', 'classe', 'groupe');
}

/**
 * Crée une vidéo enregistrée en base (sans fichier physique réel).
 */
function creerVideo(Groupe $groupe, User $auteur, array $attrs = []): GroupeVideo
{
    return GroupeVideo::create(array_merge([
        'groupe_id' => $groupe->id,
        'user_id' => $auteur->id,
        'titre' => 'Ma vidéo test',
        'nom_original' => 'video.mp4',
        'file_path' => 'medias/groupes/'.$groupe->id.'/videos/fake.mp4',
        'taille' => 1024 * 1024, // 1 Mo
        'statut' => 'brouillon',
    ], $attrs));
}
