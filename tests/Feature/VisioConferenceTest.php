<?php

use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\User;
use App\Models\VisioConference;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Crée un enseignant, un cours, une classe et un groupe liés.
 *
 * @return array{enseignant: User, cours: Cours, classe: Classe, groupe: Groupe}
 */
function creerScenarioVisio(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    $cours = Cours::create([
        'nom_cours' => 'Cours Visio Test',
        'code' => '330-VIS',
        'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);

    $classe = Classe::create(['cours_id' => $cours->id]);

    $etudiant = User::factory()->create(['role' => 'etudiant']);
    $classe->etudiants()->attach($etudiant->id);

    $groupe = Groupe::create([
        'classe_id' => $classe->id,
        'created_by' => $etudiant->id,
    ]);

    return compact('enseignant', 'cours', 'classe', 'groupe');
}

/** URL de base pour les visioconférences d'un cours. */
function urlVisio(Cours $cours, ?VisioConference $visio = null): string
{
    $base = "/cours/{$cours->id}/visio";

    return $visio ? "{$base}/{$visio->id}" : $base;
}

// ─── store() ──────────────────────────────────────────────────────────────────

test("l'enseignant peut créer une visioconférence pour son cours", function () {
    $s = creerScenarioVisio();

    $this->actingAs($s['enseignant'])
        ->post(urlVisio($s['cours']), [
            'titre' => 'Rencontre de lancement',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('visio_conferences', [
        'cours_id' => $s['cours']->id,
        'titre' => 'Rencontre de lancement',
        'animateur_id' => $s['enseignant']->id,
    ]);
});

test('la room Jitsi générée respecte le format XXXX-YYYYYYYY', function () {
    $s = creerScenarioVisio();

    $this->actingAs($s['enseignant'])
        ->post(urlVisio($s['cours']), ['titre' => 'Test room format'])
        ->assertRedirect();

    $visio = VisioConference::where('cours_id', $s['cours']->id)->first();

    // Format attendu : 4 chiffres - 8 caractères
    expect($visio->jitsi_room)->toMatch('/^\d{4}-[A-Za-z0-9]{8}$/');
});

test('deux visios créées ont des rooms distinctes', function () {
    $s = creerScenarioVisio();

    $this->actingAs($s['enseignant'])->post(urlVisio($s['cours']), ['titre' => 'Visio A']);
    $this->actingAs($s['enseignant'])->post(urlVisio($s['cours']), ['titre' => 'Visio B']);

    $rooms = VisioConference::where('cours_id', $s['cours']->id)->pluck('jitsi_room');
    expect($rooms->unique()->count())->toBe(2);
});

test("l'enseignant peut cibler un groupe spécifique lors de la création", function () {
    $s = creerScenarioVisio();

    $this->actingAs($s['enseignant'])
        ->post(urlVisio($s['cours']), [
            'titre' => 'Session ciblée',
            'groupe_id' => $s['groupe']->id,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('visio_conferences', [
        'cours_id' => $s['cours']->id,
        'groupe_id' => $s['groupe']->id,
    ]);
});

test("un enseignant d'un autre cours reçoit 403 (IDOR)", function () {
    $s = creerScenarioVisio();
    $autre = User::factory()->create(['role' => 'enseignant']);

    $this->actingAs($autre)
        ->post(urlVisio($s['cours']), ['titre' => 'Intrusion'])
        ->assertForbidden();
});

test('un étudiant sans groupe_id reçoit 403 (réservé à l\'enseignant)', function () {
    $s = creerScenarioVisio();
    $etudiant = User::factory()->create(['role' => 'etudiant']);
    $s['classe']->etudiants()->attach($etudiant->id);

    // Sans groupe_id, la création est réservée à l'enseignant — le controller retourne 403
    $this->actingAs($etudiant)
        ->post(urlVisio($s['cours']), ['titre' => 'Tentative'])
        ->assertForbidden();

    // Aucune visio créée
    $this->assertDatabaseMissing('visio_conferences', ['cours_id' => $s['cours']->id]);
});

// ─── update() ─────────────────────────────────────────────────────────────────

test("l'enseignant peut modifier le titre et la date planifiée d'une visio", function () {
    $s = creerScenarioVisio();

    $visio = VisioConference::create([
        'cours_id' => $s['cours']->id,
        'animateur_id' => $s['enseignant']->id,
        'jitsi_room' => '0001-AbCdEfGh',
        'titre' => 'Ancien titre',
    ]);

    $this->actingAs($s['enseignant'])
        ->put(urlVisio($s['cours'], $visio), [
            'titre' => 'Nouveau titre',
            'recording_url' => 'https://example.com/rec',
        ])
        ->assertRedirect();

    expect($visio->fresh()->titre)->toBe('Nouveau titre');
    expect($visio->fresh()->recording_url)->toBe('https://example.com/rec');
});

test('update rejette une URL enregistrement invalide', function () {
    $s = creerScenarioVisio();

    $visio = VisioConference::create([
        'cours_id' => $s['cours']->id,
        'animateur_id' => $s['enseignant']->id,
        'jitsi_room' => '0001-XxXxXxXx',
        'titre' => 'Test URL',
    ]);

    $this->actingAs($s['enseignant'])
        ->put(urlVisio($s['cours'], $visio), ['recording_url' => 'pas-une-url'])
        ->assertSessionHasErrors('recording_url');
});

test("update d'une visio appartenant à un autre cours retourne 404", function () {
    $autreEnseignant = User::factory()->create(['role' => 'enseignant']);
    $autreCours = Cours::create([
        'nom_cours' => 'Autre cours',
        'code' => '330-ZZZ',
        'groupe' => '01',
        'enseignant_id' => $autreEnseignant->id,
    ]);

    $s = creerScenarioVisio();

    $visioAutre = VisioConference::create([
        'cours_id' => $autreCours->id,
        'animateur_id' => $autreEnseignant->id,
        'jitsi_room' => '9999-AaBbCcDd',
        'titre' => 'Visio autre cours',
    ]);

    // L'enseignant du cours $s tente de modifier une visio d'un autre cours
    $this->actingAs($s['enseignant'])
        ->put(urlVisio($s['cours'], $visioAutre), ['titre' => 'Intrusion'])
        ->assertNotFound();
});

// ─── destroy() ────────────────────────────────────────────────────────────────

test("l'enseignant peut supprimer une visioconférence de son cours", function () {
    $s = creerScenarioVisio();

    $visio = VisioConference::create([
        'cours_id' => $s['cours']->id,
        'animateur_id' => $s['enseignant']->id,
        'jitsi_room' => '0001-DeLeTe01',
        'titre' => 'À supprimer',
    ]);

    $this->actingAs($s['enseignant'])
        ->delete(urlVisio($s['cours'], $visio))
        ->assertRedirect();

    $this->assertDatabaseMissing('visio_conferences', ['id' => $visio->id]);
});

// ─── storeRecording() ─────────────────────────────────────────────────────────

test("l'enseignant peut uploader un enregistrement sur une session terminée", function () {
    Storage::fake();

    $s = creerScenarioVisio();

    $visio = VisioConference::create([
        'cours_id' => $s['cours']->id,
        'animateur_id' => $s['enseignant']->id,
        'jitsi_room' => '0001-UpLd0001',
        'titre' => 'Session avec enregistrement',
        'ended_at' => now(),
    ]);

    $fichier = UploadedFile::fake()->create('enregistrement.mp4', 1024, 'video/mp4');

    $this->actingAs($s['enseignant'])
        ->post("/cours/{$s['cours']->id}/visio/{$visio->id}/recording", [
            'recording' => $fichier,
        ])
        ->assertRedirect();

    $visio->refresh();
    expect($visio->recording_path)->not->toBeNull();
    Storage::assertExists($visio->recording_path);
});

test("l'ancien enregistrement est supprimé et remplacé lors d'un deuxième upload", function () {
    Storage::fake();

    $s = creerScenarioVisio();

    // Créer la visio d'abord pour obtenir son id
    $visio = VisioConference::create([
        'cours_id' => $s['cours']->id,
        'animateur_id' => $s['enseignant']->id,
        'jitsi_room' => '0001-UpLd0002',
        'titre' => 'Session à remplacer',
        'ended_at' => now(),
    ]);

    // Stocker l'ancien avec une extension .avi pour avoir un chemin distinct du futur .mp4
    $ancienFichier = UploadedFile::fake()->create('ancien.avi', 100, 'video/x-msvideo');
    $ancienPath = $ancienFichier->storeAs("visio-recordings/{$visio->id}", 'recording.avi');
    $visio->update(['recording_path' => $ancienPath]);

    $nouveauFichier = UploadedFile::fake()->create('nouveau.mp4', 200, 'video/mp4');

    $this->actingAs($s['enseignant'])
        ->post("/cours/{$s['cours']->id}/visio/{$visio->id}/recording", [
            'recording' => $nouveauFichier,
        ])
        ->assertRedirect();

    // L'ancien fichier (.avi) doit être supprimé
    Storage::assertMissing($ancienPath);

    // Le nouveau chemin (.mp4) doit être enregistré en base
    $visio->refresh();
    expect($visio->recording_path)->not->toBe($ancienPath);
    Storage::assertExists($visio->recording_path);
});

test("un enseignant d'un autre cours ne peut pas uploader un enregistrement (IDOR)", function () {
    Storage::fake();

    $s = creerScenarioVisio();
    $autre = User::factory()->create(['role' => 'enseignant']);

    $visio = VisioConference::create([
        'cours_id' => $s['cours']->id,
        'animateur_id' => $s['enseignant']->id,
        'jitsi_room' => '0001-UpLd0003',
        'titre' => 'Session protégée',
        'ended_at' => now(),
    ]);

    $fichier = UploadedFile::fake()->create('enregistrement.mp4', 100, 'video/mp4');

    $this->actingAs($autre)
        ->post("/cours/{$s['cours']->id}/visio/{$visio->id}/recording", [
            'recording' => $fichier,
        ])
        ->assertForbidden();
});

test('storeRecording rejette les fichiers non-vidéo', function () {
    Storage::fake();

    $s = creerScenarioVisio();

    $visio = VisioConference::create([
        'cours_id' => $s['cours']->id,
        'animateur_id' => $s['enseignant']->id,
        'jitsi_room' => '0001-UpLd0004',
        'titre' => 'Session test',
        'ended_at' => now(),
    ]);

    $fichierInvalide = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    $this->actingAs($s['enseignant'])
        ->post("/cours/{$s['cours']->id}/visio/{$visio->id}/recording", [
            'recording' => $fichierInvalide,
        ])
        ->assertSessionHasErrors('recording');
});

// ─── streamRecording() ────────────────────────────────────────────────────────

test("un membre du cours peut streamer l'enregistrement", function () {
    Storage::fake();

    $s = creerScenarioVisio();

    $etudiant = User::factory()->create(['role' => 'etudiant']);
    $s['classe']->etudiants()->attach($etudiant->id);
    $s['groupe']->membres()->attach($etudiant->id);

    $fichier = UploadedFile::fake()->create('recording.mp4', 100, 'video/mp4');
    $path = $fichier->storeAs("visio-recordings/{$s['groupe']->id}", 'recording.mp4');

    $visio = VisioConference::create([
        'cours_id' => $s['cours']->id,
        'animateur_id' => $s['enseignant']->id,
        'jitsi_room' => '0001-StRm0001',
        'titre' => 'Session enregistrée',
        'ended_at' => now(),
        'recording_path' => $path,
    ]);

    $this->actingAs($etudiant)
        ->get("/cours/{$s['cours']->id}/visio/{$visio->id}/recording")
        ->assertOk();
});

test("l'enseignant du cours peut streamer l'enregistrement", function () {
    Storage::fake();

    $s = creerScenarioVisio();

    $fichier = UploadedFile::fake()->create('recording.mp4', 100, 'video/mp4');
    $path = $fichier->storeAs('visio-recordings/1', 'recording.mp4');

    $visio = VisioConference::create([
        'cours_id' => $s['cours']->id,
        'animateur_id' => $s['enseignant']->id,
        'jitsi_room' => '0001-StRm0002',
        'titre' => 'Session enseignant',
        'ended_at' => now(),
        'recording_path' => $path,
    ]);

    $this->actingAs($s['enseignant'])
        ->get("/cours/{$s['cours']->id}/visio/{$visio->id}/recording")
        ->assertOk();
});

test('un utilisateur non membre du cours reçoit 403 en tentant de streamer', function () {
    Storage::fake();

    $s = creerScenarioVisio();
    $inconnu = User::factory()->create(['role' => 'etudiant']);

    $fichier = UploadedFile::fake()->create('recording.mp4', 100, 'video/mp4');
    $path = $fichier->storeAs('visio-recordings/test', 'recording.mp4');

    $visio = VisioConference::create([
        'cours_id' => $s['cours']->id,
        'animateur_id' => $s['enseignant']->id,
        'jitsi_room' => '0001-StRm0003',
        'titre' => 'Session privée',
        'ended_at' => now(),
        'recording_path' => $path,
    ]);

    $this->actingAs($inconnu)
        ->get("/cours/{$s['cours']->id}/visio/{$visio->id}/recording")
        ->assertForbidden();
});

test('streamRecording retourne 404 si aucun enregistrement existe', function () {
    $s = creerScenarioVisio();

    $visio = VisioConference::create([
        'cours_id' => $s['cours']->id,
        'animateur_id' => $s['enseignant']->id,
        'jitsi_room' => '0001-StRm0004',
        'titre' => 'Session sans enregistrement',
        'ended_at' => now(),
    ]);

    $this->actingAs($s['enseignant'])
        ->get("/cours/{$s['cours']->id}/visio/{$visio->id}/recording")
        ->assertNotFound();
});

// ─── Cleanup fichier ──────────────────────────────────────────────────────────

test('le fichier enregistrement est supprimé du storage quand la visio est effacée', function () {
    Storage::fake();

    $s = creerScenarioVisio();

    $fichier = UploadedFile::fake()->create('recording.mp4', 100, 'video/mp4');
    $path = $fichier->storeAs('visio-recordings/1', 'recording.mp4');

    $visio = VisioConference::create([
        'cours_id' => $s['cours']->id,
        'animateur_id' => $s['enseignant']->id,
        'jitsi_room' => '0001-DeLRc01',
        'titre' => 'Session à supprimer avec enregistrement',
        'ended_at' => now(),
        'recording_path' => $path,
    ]);

    Storage::assertExists($path);

    $visio->delete();

    Storage::assertMissing($path);
});
