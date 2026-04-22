<?php

use App\Http\Controllers\InscriptionTemoinController;
use App\Models\Etablissement;
use App\Models\Thematique;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function creerCegep(string $nom = 'Cégep Test', string $ville = 'Montréal', ?string $code = null): Etablissement
{
    return Etablissement::create(['nom' => $nom, 'ville' => $ville, 'code' => $code]);
}

function creerEnseignantAvecCegep(?Etablissement $cegep = null): User
{
    $cegep ??= creerCegep();

    return User::factory()->create([
        'role' => 'enseignant',
        'etablissement_id' => $cegep->id,
    ]);
}

// ─── Admin : CRUD établissements ──────────────────────────────────────────────

test('admin peut créer un établissement', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->post('/administration/etablissements', [
            'nom' => 'Cégep de Sherbrooke',
            'ville' => 'Sherbrooke',
            'code' => 'CEGEP-SHE',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('etablissements', ['code' => 'CEGEP-SHE']);
});

test('admin peut modifier un établissement', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $cegep = creerCegep('Cégep Test', 'Québec', 'OLD-CODE');

    $this->actingAs($admin)
        ->put("/administration/etablissements/{$cegep->id}", [
            'nom' => 'Cégep Modifié',
            'ville' => 'Laval',
            'code' => 'NEW-CODE',
        ])
        ->assertRedirect();

    expect($cegep->fresh()->nom)->toBe('Cégep Modifié');
});

test('admin peut supprimer un établissement', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $cegep = creerCegep();

    $this->actingAs($admin)
        ->delete("/administration/etablissements/{$cegep->id}")
        ->assertRedirect();

    $this->assertDatabaseMissing('etablissements', ['id' => $cegep->id]);
});

test('non-admin ne peut pas acceder aux routes etablissements', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    // Le middleware role:admin redirige (302) vers le dashboard quand le rôle est insuffisant
    $this->actingAs($enseignant)
        ->post('/administration/etablissements', [
            'nom' => 'Test',
            'ville' => 'Ville',
        ])
        ->assertRedirect();

    $this->assertDatabaseMissing('etablissements', ['nom' => 'Test']);
});

test('creation etablissement echoue sans nom', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->post('/administration/etablissements', ['ville' => 'Montréal'])
        ->assertSessionHasErrors('nom');
});

// ─── Thématiques : scope par établissement ────────────────────────────────────

test('thematique creee par un enseignant herite de son etablissement', function () {
    $cegep = creerCegep();
    $enseignant = creerEnseignantAvecCegep($cegep);

    $this->actingAs($enseignant)
        ->post('/thematiques', [
            'nom' => 'Thème test',
            'description' => 'Description test',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('thematiques', [
        'nom' => 'Thème test',
        'enseignant_id' => $enseignant->id,
        'etablissement_id' => $cegep->id,
    ]);
});

test('enseignant voit les thematiques de tout son cegep', function () {
    $cegep = creerCegep();
    $ensA = creerEnseignantAvecCegep($cegep);
    $ensB = creerEnseignantAvecCegep($cegep);

    Thematique::factory()->create(['enseignant_id' => $ensA->id, 'etablissement_id' => $cegep->id]);
    Thematique::factory()->create(['enseignant_id' => $ensB->id, 'etablissement_id' => $cegep->id]);

    // Enseignant A doit voir les 2 thématiques du cégep
    $this->actingAs($ensA)
        ->get('/enseignant')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('thematiques', 2));
});

test('enseignant ne voit pas les thematiques d_un autre cegep', function () {
    $cegep1 = creerCegep('Cégep 1', 'Montréal', 'C1');
    $cegep2 = creerCegep('Cégep 2', 'Québec', 'C2');

    $ens1 = creerEnseignantAvecCegep($cegep1);
    $ens2 = creerEnseignantAvecCegep($cegep2);

    Thematique::factory()->create(['enseignant_id' => $ens1->id, 'etablissement_id' => $cegep1->id]);
    Thematique::factory()->create(['enseignant_id' => $ens2->id, 'etablissement_id' => $cegep2->id]);

    $this->actingAs($ens1)
        ->get('/enseignant')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('thematiques', 1));
});

// ─── Inscription témoin : choix d'établissement ───────────────────────────────

test('pa peut s_inscrire avec un etablissement valide', function () {
    $cegep = creerCegep(code: 'TEST-01');

    // Étape 1 : infos personnelles + choix cégep → stockées en session
    $this->post('/inscription/temoin', [
        'prenom' => 'Marie',
        'nom' => 'Tremblay',
        'email' => 'marie@test.com',
        'password' => 'Motdepasse1!',
        'password_confirmation' => 'Motdepasse1!',
        'choix' => [['etablissement_id' => $cegep->id, 'theme_libre' => 'Les métiers d\'autrefois']],
        'description' => 'Je suis une personne âgée intéressée par l\'histoire.',
    ])->assertRedirect(route('inscription.temoin.engagements'));

    // Étape 2 : engagements + signature → crée le compte
    $nbEngagements = InscriptionTemoinController::NB_ENGAGEMENTS;
    $this->post('/inscription/temoin/engagements', [
        'engagements' => array_fill(0, $nbEngagements, '1'),
        'signature' => 'Marie Tremblay',
    ])->assertRedirect();

    $user = User::where('email', 'marie@test.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->role)->toBe('personne_agee');

    expect($user->etablissementsChoisis()->where('etablissements.id', $cegep->id)->exists())->toBeTrue();
});

test('pa ne peut pas s_inscrire sans etablissement', function () {
    $this->post('/inscription/temoin', [
        'prenom' => 'Marie',
        'nom' => 'Tremblay',
        'email' => 'marie@test.com',
        'password' => 'Motdepasse1!',
        'password_confirmation' => 'Motdepasse1!',
        'description' => 'Description.',
        'theme_libre' => 'Thème libre',
    ])->assertSessionHasErrors('choix');
});

test('page inscription temoin retourne les etablissements', function () {
    creerCegep('Cégep A', 'Montréal', 'A');
    creerCegep('Cégep B', 'Québec', 'B');

    $this->get('/inscription/temoin')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('etablissements', 2));
});

// ─── Suppression établissement : SET NULL sur les FK ─────────────────────────

test('supprimer un etablissement met etablissement_id a null sur les users', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $cegep = creerCegep();
    $enseignant = creerEnseignantAvecCegep($cegep);

    $this->actingAs($admin)
        ->delete("/administration/etablissements/{$cegep->id}")
        ->assertRedirect();

    expect($enseignant->fresh()->etablissement_id)->toBeNull();
});
