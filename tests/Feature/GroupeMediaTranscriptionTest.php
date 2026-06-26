<?php

use App\Jobs\TranscrireGroupeMedia;
use App\Models\Groupe;
use App\Models\GroupeMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/**
 * Crée un enregistrement GroupeMedia de type audio (sans fichier physique réel).
 */
function creerMediaAudio(Groupe $groupe, User $auteur, array $extra = []): GroupeMedia
{
    return GroupeMedia::create([
        'groupe_id' => $groupe->id,
        'user_id' => $auteur->id,
        'nom_original' => 'message.mp3',
        'file_path' => "images/groupes/{$groupe->id}/message.mp3",
        'type' => 'audio',
        'taille' => 102400,
        ...$extra,
    ]);
}

// ─── transcrire() — Transcription Whisper ─────────────────────────────────────

test('un membre peut déclencher la transcription d\'un message vocal', function () {
    Queue::fake();

    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe] = creerScenarioVideo();

    $media = creerMediaAudio($groupe, $etudiant);

    $this->actingAs($etudiant)
        ->post(route('groupes.medias.transcrire', [$cours, $classe, $groupe, $media]))
        ->assertRedirect();

    Queue::assertPushed(TranscrireGroupeMedia::class, function ($job) use ($media) {
        return $job->media->id === $media->id;
    });

    expect($media->fresh()->transcription_statut)->toBe(GroupeMedia::TRANSCRIPTION_EN_ATTENTE);
});

test('l\'enseignant peut déclencher la transcription d\'un message vocal', function () {
    Queue::fake();

    ['enseignant' => $enseignant, 'etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe] = creerScenarioVideo();

    $media = creerMediaAudio($groupe, $etudiant);

    $this->actingAs($enseignant)
        ->post(route('groupes.medias.transcrire', [$cours, $classe, $groupe, $media]))
        ->assertRedirect();

    Queue::assertPushed(TranscrireGroupeMedia::class);
    expect($media->fresh()->transcription_statut)->toBe(GroupeMedia::TRANSCRIPTION_EN_ATTENTE);
});

test('un tiers ne peut pas déclencher la transcription d\'un message vocal', function () {
    Queue::fake();

    ['cours' => $cours, 'classe' => $classe, 'groupe' => $groupe, 'etudiant' => $auteur] = creerScenarioVideo();

    $media = creerMediaAudio($groupe, $auteur);

    $tiers = User::factory()->create(['role' => 'etudiant']);

    $this->actingAs($tiers)
        ->post(route('groupes.medias.transcrire', [$cours, $classe, $groupe, $media]))
        ->assertForbidden();

    Queue::assertNothingPushed();
});

test('transcrire ne redispatche pas si la transcription est déjà en cours', function () {
    Queue::fake();

    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe] = creerScenarioVideo();

    $media = creerMediaAudio($groupe, $etudiant, [
        'transcription_statut' => GroupeMedia::TRANSCRIPTION_EN_COURS,
    ]);

    $this->actingAs($etudiant)
        ->post(route('groupes.medias.transcrire', [$cours, $classe, $groupe, $media]))
        ->assertRedirect();

    Queue::assertNothingPushed();
});

test('transcrire ne redispatche pas si la transcription est en attente', function () {
    Queue::fake();

    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe] = creerScenarioVideo();

    $media = creerMediaAudio($groupe, $etudiant, [
        'transcription_statut' => GroupeMedia::TRANSCRIPTION_EN_ATTENTE,
    ]);

    $this->actingAs($etudiant)
        ->post(route('groupes.medias.transcrire', [$cours, $classe, $groupe, $media]))
        ->assertRedirect();

    Queue::assertNothingPushed();
});

test('transcrire retourne 422 si le média n\'est pas de type audio', function () {
    Queue::fake();

    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe] = creerScenarioVideo();

    $media = GroupeMedia::create([
        'groupe_id' => $groupe->id,
        'user_id' => $etudiant->id,
        'nom_original' => 'photo.jpg',
        'file_path' => "images/groupes/{$groupe->id}/photo.jpg",
        'type' => 'photo',
        'taille' => 5000,
    ]);

    $this->actingAs($etudiant)
        ->post(route('groupes.medias.transcrire', [$cours, $classe, $groupe, $media]))
        ->assertStatus(422);

    Queue::assertNothingPushed();
});
