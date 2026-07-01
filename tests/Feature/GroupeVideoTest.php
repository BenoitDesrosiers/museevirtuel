<?php

use App\Jobs\ProcessVideoEdit;
use App\Jobs\ProcessVideoMerge;
use App\Jobs\TranscrireVideo;
use App\Models\Groupe;
use App\Models\GroupeVideo;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;

// ─── store() — Upload ──────────────────────────────────────────────────────────

test('un membre peut uploader une vidéo', function () {
    Queue::fake();

    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe] = creerScenarioVideo();

    $file = UploadedFile::fake()->create('interview.mp4', 1024, 'video/mp4');

    $this->actingAs($etudiant)
        ->post(
            route('groupes.videos.store', [$cours, $classe, $groupe]),
            ['titre' => 'Mon entrevue', 'fichier' => $file],
        )
        ->assertRedirect();

    $this->assertDatabaseHas('groupe_videos', [
        'groupe_id' => $groupe->id,
        'user_id' => $etudiant->id,
        'titre' => 'Mon entrevue',
        'statut' => 'brouillon',
        'transcription_statut' => GroupeVideo::TRANSCRIPTION_EN_ATTENTE,
    ]);
});

test('l\'upload déclenche automatiquement la transcription', function () {
    Queue::fake();

    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe] = creerScenarioVideo();

    $file = UploadedFile::fake()->create('cours.mp4', 1024, 'video/mp4');

    $this->actingAs($etudiant)
        ->post(
            route('groupes.videos.store', [$cours, $classe, $groupe]),
            ['titre' => 'Cours magistral', 'fichier' => $file],
        )
        ->assertRedirect();

    Queue::assertPushed(TranscrireVideo::class);
});

test('un non-membre ne peut pas uploader une vidéo', function () {
    ['cours' => $cours, 'classe' => $classe, 'groupe' => $groupe] = creerScenarioVideo();

    $etranger = User::factory()->create(['role' => 'etudiant']);

    $file = UploadedFile::fake()->create('video.mp4', 100, 'video/mp4');

    $this->actingAs($etranger)
        ->post(
            route('groupes.videos.store', [$cours, $classe, $groupe]),
            ['titre' => 'Intrusion', 'fichier' => $file],
        )
        ->assertForbidden();
});

test('un type de fichier invalide est refusé', function () {
    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe] = creerScenarioVideo();

    $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    $this->actingAs($etudiant)
        ->post(
            route('groupes.videos.store', [$cours, $classe, $groupe]),
            ['titre' => 'PDF invalide', 'fichier' => $file],
        )
        ->assertSessionHasErrors('fichier');
});

test('un fichier trop volumineux est refusé', function () {
    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe] = creerScenarioVideo();

    // 2097153 Ko = >2 Go (limite actuelle)
    $file = UploadedFile::fake()->create('grosse-video.mp4', 2097153, 'video/mp4');

    $this->actingAs($etudiant)
        ->post(
            route('groupes.videos.store', [$cours, $classe, $groupe]),
            ['titre' => 'Vidéo trop lourde', 'fichier' => $file],
        )
        ->assertSessionHasErrors('fichier');
});

test('le titre est obligatoire', function () {
    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe] = creerScenarioVideo();

    $file = UploadedFile::fake()->create('video.mp4', 100, 'video/mp4');

    $this->actingAs($etudiant)
        ->post(
            route('groupes.videos.store', [$cours, $classe, $groupe]),
            ['titre' => '', 'fichier' => $file],
        )
        ->assertSessionHasErrors('titre');
});

// ─── publier() — Publication ───────────────────────────────────────────────────

test('l\'auteur peut publier sa propre vidéo', function () {
    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe] = creerScenarioVideo();

    $video = creerVideo($groupe, $etudiant);

    $this->actingAs($etudiant)
        ->post(route('groupes.videos.publier', [$cours, $classe, $groupe, $video]))
        ->assertRedirect();

    expect($video->fresh()->statut)->toBe('publié');
});

test('l\'enseignant peut publier n\'importe quelle vidéo du groupe', function () {
    ['enseignant' => $enseignant, 'etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe] = creerScenarioVideo();

    $video = creerVideo($groupe, $etudiant);

    $this->actingAs($enseignant)
        ->post(route('groupes.videos.publier', [$cours, $classe, $groupe, $video]))
        ->assertRedirect();

    expect($video->fresh()->statut)->toBe('publié');
});

test('un tiers ne peut pas publier une vidéo', function () {
    ['cours' => $cours, 'classe' => $classe, 'groupe' => $groupe, 'etudiant' => $etudiant] = creerScenarioVideo();

    $video = creerVideo($groupe, $etudiant);

    $tiers = User::factory()->create(['role' => 'etudiant']);

    $this->actingAs($tiers)
        ->post(route('groupes.videos.publier', [$cours, $classe, $groupe, $video]))
        ->assertForbidden();

    expect($video->fresh()->statut)->toBe('brouillon');
});

// ─── destroy() — Suppression ───────────────────────────────────────────────────

test('l\'auteur peut supprimer sa propre vidéo', function () {
    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe] = creerScenarioVideo();

    $video = creerVideo($groupe, $etudiant);

    $this->actingAs($etudiant)
        ->delete(route('groupes.videos.destroy', [$cours, $classe, $groupe, $video]))
        ->assertRedirect();

    $this->assertDatabaseMissing('groupe_videos', ['id' => $video->id]);
});

test('un tiers ne peut pas supprimer une vidéo', function () {
    ['cours' => $cours, 'classe' => $classe, 'groupe' => $groupe, 'etudiant' => $etudiant] = creerScenarioVideo();

    $video = creerVideo($groupe, $etudiant);
    $tiers = User::factory()->create(['role' => 'etudiant']);

    $this->actingAs($tiers)
        ->delete(route('groupes.videos.destroy', [$cours, $classe, $groupe, $video]))
        ->assertForbidden();

    $this->assertDatabaseHas('groupe_videos', ['id' => $video->id]);
});

test('l\'enseignant peut supprimer une vidéo du groupe', function () {
    ['enseignant' => $enseignant, 'etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe] = creerScenarioVideo();

    $video = creerVideo($groupe, $etudiant);

    $this->actingAs($enseignant)
        ->delete(route('groupes.videos.destroy', [$cours, $classe, $groupe, $video]))
        ->assertRedirect();

    $this->assertDatabaseMissing('groupe_videos', ['id' => $video->id]);
});

// ─── editer() — Dispatch du Job ────────────────────────────────────────────────

test('un coéquipier peut éditer la vidéo d\'un autre membre du groupe', function () {
    Queue::fake();

    ['cours' => $cours, 'classe' => $classe, 'groupe' => $groupe, 'etudiant' => $auteur] = creerScenarioVideo();

    // Deuxième étudiant membre du même groupe.
    $coequipier = User::factory()->create(['role' => 'etudiant']);
    $classe->etudiants()->attach($coequipier->id);
    $groupe->membres()->attach($coequipier->id);

    $video = creerVideo($groupe, $auteur, ['duree' => 60]);

    $this->actingAs($coequipier)
        ->post(
            route('groupes.videos.editer', [$cours, $classe, $groupe, $video]),
            ['debut' => 0, 'fin' => 60, 'coupes' => []],
        )
        ->assertRedirect();

    Queue::assertPushed(ProcessVideoEdit::class);
});

test('un étudiant hors du groupe ne peut pas éditer une vidéo', function () {
    ['cours' => $cours, 'classe' => $classe, 'groupe' => $groupe, 'etudiant' => $auteur] = creerScenarioVideo();

    $etranger = User::factory()->create(['role' => 'etudiant']);
    $video = creerVideo($groupe, $auteur, ['duree' => 60]);

    $this->actingAs($etranger)
        ->post(
            route('groupes.videos.editer', [$cours, $classe, $groupe, $video]),
            ['debut' => 0, 'fin' => 60, 'coupes' => []],
        )
        ->assertForbidden();
});

test('editer dispatche ProcessVideoEdit avec les bons paramètres', function () {
    Queue::fake();

    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe] = creerScenarioVideo();

    $video = creerVideo($groupe, $etudiant, ['duree' => 60]);

    $this->actingAs($etudiant)
        ->post(
            route('groupes.videos.editer', [$cours, $classe, $groupe, $video]),
            [
                'debut' => 5,
                'fin' => 55,
                'coupes' => [
                    ['debut' => 20, 'fin' => 30],
                ],
            ],
        )
        ->assertRedirect();

    Queue::assertPushed(ProcessVideoEdit::class, function ($job) use ($video) {
        return $job->video->id === $video->id
            && $job->debut === 5.0
            && $job->fin === 55.0
            && count($job->coupes) === 1;
    });

    expect($video->fresh()->traitement_statut)->toBe(GroupeVideo::TRAITEMENT_EN_ATTENTE);
});

test('editer refuse une coupe avec fin <= debut', function () {
    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe] = creerScenarioVideo();

    $video = creerVideo($groupe, $etudiant, ['duree' => 60]);

    $this->actingAs($etudiant)
        ->post(
            route('groupes.videos.editer', [$cours, $classe, $groupe, $video]),
            [
                'debut' => 5,
                'fin' => 55,
                'coupes' => [
                    ['debut' => 30, 'fin' => 20], // invalide
                ],
            ],
        )
        ->assertSessionHasErrors('coupes.0.fin');
});

test('une vidéo en cours de traitement ne peut pas être éditée', function () {
    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe] = creerScenarioVideo();

    $video = creerVideo($groupe, $etudiant, ['traitement_statut' => GroupeVideo::TRAITEMENT_EN_COURS]);

    $this->actingAs($etudiant)
        ->post(
            route('groupes.videos.editer', [$cours, $classe, $groupe, $video]),
            ['debut' => 0, 'fin' => 30, 'coupes' => []],
        )
        ->assertForbidden();
});

// ─── jumeler() — Insertion d'une vidéo dans une autre ─────────────────────────

test('un membre peut jumeler deux vidéos du même groupe', function () {
    Queue::fake();

    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe] = creerScenarioVideo();

    $videoBase = creerVideo($groupe, $etudiant, ['titre' => 'Base']);
    $videoInsert = creerVideo($groupe, $etudiant, ['titre' => 'À insérer']);

    $this->actingAs($etudiant)
        ->post(
            route('groupes.videos.jumeler', [$cours, $classe, $groupe, $videoBase]),
            ['video_a_inserer_id' => $videoInsert->id, 'position' => 10],
        )
        ->assertRedirect();

    Queue::assertPushed(ProcessVideoMerge::class);
    expect($videoBase->fresh()->traitement_statut)->toBe(GroupeVideo::TRAITEMENT_EN_ATTENTE);
});

test('jumeler une vidéo avec elle-même retourne 422', function () {
    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe] = creerScenarioVideo();

    $video = creerVideo($groupe, $etudiant);

    $this->actingAs($etudiant)
        ->post(
            route('groupes.videos.jumeler', [$cours, $classe, $groupe, $video]),
            ['video_a_inserer_id' => $video->id, 'position' => 5],
        )
        ->assertStatus(422);
});

test('jumeler avec une vidéo d\'un autre groupe est refusé', function () {
    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe] = creerScenarioVideo();

    $videoBase = creerVideo($groupe, $etudiant);

    // Crée un second groupe dans la même classe avec sa propre vidéo.
    $autreGroupe = Groupe::create([
        'classe_id' => $classe->id,
        'created_by' => $etudiant->id,
    ]);
    $videoAutreGroupe = creerVideo($autreGroupe, $etudiant);

    $this->actingAs($etudiant)
        ->post(
            route('groupes.videos.jumeler', [$cours, $classe, $groupe, $videoBase]),
            ['video_a_inserer_id' => $videoAutreGroupe->id, 'position' => 5],
        )
        ->assertForbidden();
});

test('jumeler une vidéo en cours de traitement est refusé', function () {
    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe] = creerScenarioVideo();

    $videoBase = creerVideo($groupe, $etudiant, ['traitement_statut' => GroupeVideo::TRAITEMENT_EN_COURS]);
    $videoInsert = creerVideo($groupe, $etudiant);

    $this->actingAs($etudiant)
        ->post(
            route('groupes.videos.jumeler', [$cours, $classe, $groupe, $videoBase]),
            ['video_a_inserer_id' => $videoInsert->id, 'position' => 5],
        )
        ->assertForbidden();
});

test('un non-membre ne peut pas jumeler des vidéos', function () {
    ['cours' => $cours, 'classe' => $classe, 'groupe' => $groupe, 'etudiant' => $auteur] = creerScenarioVideo();

    $videoBase = creerVideo($groupe, $auteur);
    $videoInsert = creerVideo($groupe, $auteur);
    $etranger = User::factory()->create(['role' => 'etudiant']);

    $this->actingAs($etranger)
        ->post(
            route('groupes.videos.jumeler', [$cours, $classe, $groupe, $videoBase]),
            ['video_a_inserer_id' => $videoInsert->id, 'position' => 5],
        )
        ->assertForbidden();
});

// ─── transcrire() — Transcription Whisper ─────────────────────────────────────

test('l\'auteur peut déclencher la transcription de sa vidéo', function () {
    Queue::fake();

    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe] = creerScenarioVideo();

    $video = creerVideo($groupe, $etudiant);

    $this->actingAs($etudiant)
        ->post(route('groupes.videos.transcrire', [$cours, $classe, $groupe, $video]))
        ->assertRedirect();

    Queue::assertPushed(TranscrireVideo::class, function ($job) use ($video) {
        return $job->video->id === $video->id;
    });

    expect($video->fresh()->transcription_statut)->toBe(GroupeVideo::TRANSCRIPTION_EN_ATTENTE);
});

test('l\'enseignant peut déclencher la transcription d\'une vidéo du groupe', function () {
    Queue::fake();

    ['enseignant' => $enseignant, 'etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe] = creerScenarioVideo();

    $video = creerVideo($groupe, $etudiant);

    $this->actingAs($enseignant)
        ->post(route('groupes.videos.transcrire', [$cours, $classe, $groupe, $video]))
        ->assertRedirect();

    Queue::assertPushed(TranscrireVideo::class);
    expect($video->fresh()->transcription_statut)->toBe(GroupeVideo::TRANSCRIPTION_EN_ATTENTE);
});

test('un tiers ne peut pas déclencher la transcription', function () {
    Queue::fake();

    ['cours' => $cours, 'classe' => $classe, 'groupe' => $groupe, 'etudiant' => $auteur] = creerScenarioVideo();

    $video = creerVideo($groupe, $auteur);
    $tiers = User::factory()->create(['role' => 'etudiant']);

    $this->actingAs($tiers)
        ->post(route('groupes.videos.transcrire', [$cours, $classe, $groupe, $video]))
        ->assertForbidden();

    Queue::assertNothingPushed();
});

test('transcrire ne redispatche pas si la transcription est déjà en cours', function () {
    Queue::fake();

    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe] = creerScenarioVideo();

    // Transcription déjà lancée.
    $video = creerVideo($groupe, $etudiant, [
        'transcription_statut' => GroupeVideo::TRANSCRIPTION_EN_COURS,
    ]);

    $this->actingAs($etudiant)
        ->post(route('groupes.videos.transcrire', [$cours, $classe, $groupe, $video]))
        ->assertRedirect();

    // Le Job ne doit pas être dispatché une seconde fois.
    Queue::assertNothingPushed();
});

test('transcrire ne redispatche pas si la transcription est en attente', function () {
    Queue::fake();

    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe] = creerScenarioVideo();

    $video = creerVideo($groupe, $etudiant, [
        'transcription_statut' => GroupeVideo::TRANSCRIPTION_EN_ATTENTE,
    ]);

    $this->actingAs($etudiant)
        ->post(route('groupes.videos.transcrire', [$cours, $classe, $groupe, $video]))
        ->assertRedirect();

    Queue::assertNothingPushed();
});

// ─── statut() — Endpoint de polling ───────────────────────────────────────────

test('statut() retourne transcription_statut, transcription et transcription_segments', function () {
    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe] = creerScenarioVideo();

    $segments = [
        ['start' => 0.0, 'end' => 4.5, 'text' => 'Bonjour tout le monde'],
        ['start' => 4.5, 'end' => 9.2, 'text' => 'voici le texte transcrit'],
    ];

    $video = creerVideo($groupe, $etudiant, [
        'transcription_statut' => GroupeVideo::TRANSCRIPTION_TERMINEE,
        'transcription' => 'Bonjour tout le monde voici le texte transcrit',
        'transcription_segments' => $segments,
    ]);

    $this->actingAs($etudiant)
        ->getJson(route('groupes.videos.statut', [$cours, $classe, $groupe, $video]))
        ->assertSuccessful()
        ->assertJsonFragment([
            'transcription_statut' => GroupeVideo::TRANSCRIPTION_TERMINEE,
            'transcription' => 'Bonjour tout le monde voici le texte transcrit',
        ])
        ->assertJsonCount(2, 'transcription_segments');
});

test('statut() retourne transcription null si aucune transcription', function () {
    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe] = creerScenarioVideo();

    $video = creerVideo($groupe, $etudiant);

    $this->actingAs($etudiant)
        ->getJson(route('groupes.videos.statut', [$cours, $classe, $groupe, $video]))
        ->assertSuccessful()
        ->assertJsonFragment([
            'transcription_statut' => null,
            'transcription' => null,
            'transcription_segments' => null,
        ]);
});
