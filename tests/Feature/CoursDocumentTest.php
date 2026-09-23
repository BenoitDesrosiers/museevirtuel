<?php

use App\Models\Cours;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

/**
 * Crée un enseignant et un cours minimal pour les tests de documents.
 *
 * @return array{enseignant: User, cours: Cours}
 */
function creerScenarioDocument(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    $cours = Cours::create([
        'nom_cours' => 'Histoire du Québec',
        'code' => '330-DOC',
        'groupe' => '00001',
        'enseignant_id' => $enseignant->id,
    ]);

    return compact('enseignant', 'cours');
}

test('store refuse un format invalide avec message en francais', function () {
    ['enseignant' => $enseignant, 'cours' => $cours] = creerScenarioDocument();

    $this->actingAs($enseignant)
        ->from("/cours/{$cours->id}")
        ->post("/cours/{$cours->id}/documents", [
            'document' => UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg'),
        ])
        ->assertSessionHasErrors(['document'])
        ->assertSessionHasErrors([
            'document' => __('document.format_error'),
        ]);
});

test('download renvoie le fichier avec le nom original', function () {
    ['enseignant' => $enseignant, 'cours' => $cours] = creerScenarioDocument();

    $this->actingAs($enseignant)
        ->post("/cours/{$cours->id}/documents", [
            'document' => UploadedFile::fake()->create('consignes.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect();

    $document = $cours->documents()->first();

    $this->actingAs($enseignant)
        ->get("/cours/{$cours->id}/documents/{$document->id}/download")
        ->assertOk()
        ->assertDownload('consignes.pdf');
});

test('download retourne 404 si le document n appartient pas au cours', function () {
    ['enseignant' => $enseignant, 'cours' => $cours] = creerScenarioDocument();
    ['cours' => $autreCours] = creerScenarioDocument();

    $this->actingAs($enseignant)
        ->post("/cours/{$cours->id}/documents", [
            'document' => UploadedFile::fake()->create('consignes.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect();

    $document = $cours->documents()->first();

    $this->actingAs($enseignant)
        ->get("/cours/{$autreCours->id}/documents/{$document->id}/download")
        ->assertNotFound();
});

test('destroy supprime le document du cours', function () {
    ['enseignant' => $enseignant, 'cours' => $cours] = creerScenarioDocument();

    $this->actingAs($enseignant)
        ->post("/cours/{$cours->id}/documents", [
            'document' => UploadedFile::fake()->create('consignes.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect();

    $document = $cours->documents()->first();

    $this->actingAs($enseignant)
        ->delete("/cours/{$cours->id}/documents/{$document->id}")
        ->assertRedirect();

    $this->assertDatabaseMissing('cours_documents', ['id' => $document->id]);
});

test('store accepte un document pdf', function () {
    ['enseignant' => $enseignant, 'cours' => $cours] = creerScenarioDocument();

    $this->actingAs($enseignant)
        ->post("/cours/{$cours->id}/documents", [
            'document' => UploadedFile::fake()->create('consignes.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('cours_documents', [
        'cours_id' => $cours->id,
        'enseignant_id' => $enseignant->id,
        'nom_original' => 'consignes.pdf',
    ]);
});
