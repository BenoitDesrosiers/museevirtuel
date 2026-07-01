<?php

use App\Models\GroupeVideo;
use App\Models\GroupeVideoChapitre;
use App\Models\User;
use Illuminate\Http\UploadedFile;

// ─── Chapitres — store() ───────────────────────────────────────────────────────

test('un membre peut ajouter un chapitre à une vidéo', function () {
    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe] = creerScenarioVideo();
    $video = creerVideo($groupe, $etudiant, ['statut' => 'publié']);

    $this->actingAs($etudiant)
        ->post(
            route('groupes.videos.chapitres.store', [$cours, $classe, $groupe, $video]),
            ['label' => 'Introduction', 'debut' => 0, 'fin' => 120],
        )
        ->assertRedirect();

    $this->assertDatabaseHas('groupe_video_chapitres', [
        'video_id' => $video->id,
        'label' => 'Introduction',
        'debut' => 0.0,
        'fin' => 120.0,
    ]);
});

test('un chapitre sans fin est autorisé', function () {
    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe] = creerScenarioVideo();
    $video = creerVideo($groupe, $etudiant);

    $this->actingAs($etudiant)
        ->post(
            route('groupes.videos.chapitres.store', [$cours, $classe, $groupe, $video]),
            ['label' => 'Conclusion', 'debut' => 300],
        )
        ->assertRedirect();

    $this->assertDatabaseHas('groupe_video_chapitres', [
        'video_id' => $video->id,
        'label' => 'Conclusion',
        'fin' => null,
    ]);
});

test('l\'ordre est auto-incrémenté à chaque chapitre ajouté', function () {
    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe] = creerScenarioVideo();
    $video = creerVideo($groupe, $etudiant);

    $url = route('groupes.videos.chapitres.store', [$cours, $classe, $groupe, $video]);

    $this->actingAs($etudiant)->post($url, ['label' => 'A', 'debut' => 0]);
    $this->actingAs($etudiant)->post($url, ['label' => 'B', 'debut' => 60]);

    $chapitres = GroupeVideoChapitre::where('video_id', $video->id)->orderBy('ordre')->get();

    expect($chapitres[0]->ordre)->toBe(1)
        ->and($chapitres[1]->ordre)->toBe(2);
});

test('un non-membre ne peut pas ajouter un chapitre', function () {
    ['cours' => $cours, 'classe' => $classe, 'groupe' => $groupe, 'etudiant' => $etudiant] = creerScenarioVideo();
    $video = creerVideo($groupe, $etudiant);

    $inconnu = User::factory()->create(['role' => 'etudiant']);

    $this->actingAs($inconnu)
        ->post(
            route('groupes.videos.chapitres.store', [$cours, $classe, $groupe, $video]),
            ['label' => 'Intro', 'debut' => 0],
        )
        ->assertForbidden();
});

test('le label est obligatoire', function () {
    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe] = creerScenarioVideo();
    $video = creerVideo($groupe, $etudiant);

    $this->actingAs($etudiant)
        ->post(
            route('groupes.videos.chapitres.store', [$cours, $classe, $groupe, $video]),
            ['debut' => 0],
        )
        ->assertSessionHasErrors('label');
});

// ─── Chapitres — update() ──────────────────────────────────────────────────────

test('un membre peut modifier un chapitre', function () {
    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe] = creerScenarioVideo();
    $video = creerVideo($groupe, $etudiant);
    $chapitre = GroupeVideoChapitre::create([
        'video_id' => $video->id,
        'label' => 'Ancien titre',
        'debut' => 0.0,
        'fin' => 60.0,
        'ordre' => 1,
    ]);

    $this->actingAs($etudiant)
        ->patch(
            route('groupes.videos.chapitres.update', [$cours, $classe, $groupe, $video, $chapitre]),
            ['label' => 'Nouveau titre', 'debut' => 5.0, 'fin' => 65.0],
        )
        ->assertRedirect();

    expect($chapitre->fresh()->label)->toBe('Nouveau titre')
        ->and($chapitre->fresh()->debut)->toBe(5.0);
});

test('on ne peut pas modifier un chapitre appartenant à une autre vidéo', function () {
    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe] = creerScenarioVideo();
    $video = creerVideo($groupe, $etudiant);
    $autreVideo = creerVideo($groupe, $etudiant, ['titre' => 'Autre']);

    $chapitreAutreVideo = GroupeVideoChapitre::create([
        'video_id' => $autreVideo->id,
        'label' => 'Chapitre ailleurs',
        'debut' => 0.0,
        'ordre' => 1,
    ]);

    $this->actingAs($etudiant)
        ->patch(
            route('groupes.videos.chapitres.update', [$cours, $classe, $groupe, $video, $chapitreAutreVideo]),
            ['label' => 'Injection', 'debut' => 0.0],
        )
        ->assertForbidden();
});

// ─── Chapitres — destroy() ─────────────────────────────────────────────────────

test('un membre peut supprimer un chapitre', function () {
    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe] = creerScenarioVideo();
    $video = creerVideo($groupe, $etudiant);
    $chapitre = GroupeVideoChapitre::create([
        'video_id' => $video->id,
        'label' => 'À supprimer',
        'debut' => 0.0,
        'ordre' => 1,
    ]);

    $this->actingAs($etudiant)
        ->delete(route('groupes.videos.chapitres.destroy', [$cours, $classe, $groupe, $video, $chapitre]))
        ->assertRedirect();

    $this->assertDatabaseMissing('groupe_video_chapitres', ['id' => $chapitre->id]);
});

// ─── modifierTranscription() ───────────────────────────────────────────────────

test('l\'auteur peut modifier la transcription par segments', function () {
    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe] = creerScenarioVideo();
    $video = creerVideo($groupe, $etudiant, [
        'transcription' => 'Texte original.',
        'transcription_segments' => [['start' => 0, 'end' => 3, 'text' => 'Texte original.']],
        'transcription_statut' => GroupeVideo::TRANSCRIPTION_TERMINEE,
    ]);

    $this->actingAs($etudiant)
        ->post(
            route('groupes.videos.transcription.modifier', [$cours, $classe, $groupe, $video]),
            [
                'segments' => [
                    ['start' => 0, 'end' => 3, 'text' => 'Texte corrigé.'],
                ],
            ],
        )
        ->assertRedirect();

    $video->refresh();

    expect($video->transcription)->toBe('Texte corrigé.')
        ->and($video->transcription_modifiee)->toBeTrue()
        ->and($video->transcription_segments[0]['text'])->toBe('Texte corrigé.');
});

test('un non-auteur ne peut pas modifier la transcription', function () {
    ['cours' => $cours, 'classe' => $classe, 'groupe' => $groupe, 'etudiant' => $etudiant] = creerScenarioVideo();
    $video = creerVideo($groupe, $etudiant, [
        'transcription_statut' => GroupeVideo::TRANSCRIPTION_TERMINEE,
    ]);

    $inconnu = User::factory()->create(['role' => 'etudiant']);

    $this->actingAs($inconnu)
        ->post(
            route('groupes.videos.transcription.modifier', [$cours, $classe, $groupe, $video]),
            ['segments' => [['start' => 0, 'end' => 3, 'text' => 'Injection.']]],
        )
        ->assertForbidden();
});

test('la transcription reconstruite est la concaténation des segments', function () {
    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe] = creerScenarioVideo();
    $video = creerVideo($groupe, $etudiant, [
        'transcription_statut' => GroupeVideo::TRANSCRIPTION_TERMINEE,
    ]);

    $this->actingAs($etudiant)
        ->post(
            route('groupes.videos.transcription.modifier', [$cours, $classe, $groupe, $video]),
            [
                'segments' => [
                    ['start' => 0, 'end' => 2, 'text' => 'Premier segment.'],
                    ['start' => 2, 'end' => 5, 'text' => 'Deuxième segment.'],
                ],
            ],
        )
        ->assertRedirect();

    expect($video->fresh()->transcription)->toBe('Premier segment. Deuxième segment.');
});

// ─── importerTranscription() ───────────────────────────────────────────────────

test('l\'auteur peut importer une transcription .txt', function () {
    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe] = creerScenarioVideo();
    $video = creerVideo($groupe, $etudiant);

    $fichier = UploadedFile::fake()->createWithContent('transcription.txt', 'Bonjour le monde.');

    $this->actingAs($etudiant)
        ->post(
            route('groupes.videos.transcription.importer', [$cours, $classe, $groupe, $video]),
            ['fichier' => $fichier],
        )
        ->assertRedirect();

    $video->refresh();

    expect($video->transcription)->toBe('Bonjour le monde.')
        ->and($video->transcription_modifiee)->toBeTrue()
        ->and($video->transcription_statut)->toBe(GroupeVideo::TRANSCRIPTION_TERMINEE);
});

test('l\'auteur peut importer une transcription .srt', function () {
    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe] = creerScenarioVideo();
    $video = creerVideo($groupe, $etudiant);

    $contenu = "1\n00:00:01,000 --> 00:00:03,000\nBonjour.\n";
    $fichier = UploadedFile::fake()->createWithContent('transcription.srt', $contenu);

    $this->actingAs($etudiant)
        ->post(
            route('groupes.videos.transcription.importer', [$cours, $classe, $groupe, $video]),
            ['fichier' => $fichier],
        )
        ->assertRedirect();

    $video->refresh();

    expect($video->transcription)->toBe('Bonjour.')
        ->and($video->transcription_segments)->toHaveCount(1);
});

test('un format de fichier non supporté est refusé', function () {
    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe] = creerScenarioVideo();
    $video = creerVideo($groupe, $etudiant);

    $fichier = UploadedFile::fake()->createWithContent('transcription.pdf', '%PDF');

    $this->actingAs($etudiant)
        ->post(
            route('groupes.videos.transcription.importer', [$cours, $classe, $groupe, $video]),
            ['fichier' => $fichier],
        )
        ->assertSessionHasErrors('fichier');
});
