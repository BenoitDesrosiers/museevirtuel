<?php

use App\Services\ImportTranscriptionService;
use Illuminate\Http\UploadedFile;

$service = new ImportTranscriptionService;

// ─── fromTexte() ───────────────────────────────────────────────────────────────

test('fromTexte retourne le texte brut et aucun segment', function () use ($service) {
    $resultat = $service->fromTexte("Bonjour le monde.\nDeuxième ligne.");

    expect($resultat['transcription'])->toBe("Bonjour le monde.\nDeuxième ligne.")
        ->and($resultat['segments'])->toBeNull();
});

test('fromTexte supprime les espaces en début et fin', function () use ($service) {
    $resultat = $service->fromTexte("  texte  \n");

    expect($resultat['transcription'])->toBe('texte');
});

// ─── fromSrt() ─────────────────────────────────────────────────────────────────

test('fromSrt parse un fichier SRT valide', function () use ($service) {
    $srt = <<<'SRT'
    1
    00:00:01,000 --> 00:00:04,500
    Bonjour tout le monde.

    2
    00:00:05,000 --> 00:00:08,000
    Ceci est un test.
    SRT;

    $resultat = $service->fromSrt($srt);

    expect($resultat['segments'])->toHaveCount(2)
        ->and($resultat['segments'][0]['start'])->toBe(1.0)
        ->and($resultat['segments'][0]['end'])->toBe(4.5)
        ->and($resultat['segments'][0]['text'])->toBe('Bonjour tout le monde.')
        ->and($resultat['segments'][1]['start'])->toBe(5.0)
        ->and($resultat['segments'][1]['end'])->toBe(8.0)
        ->and($resultat['segments'][1]['text'])->toBe('Ceci est un test.')
        ->and($resultat['transcription'])->toBe('Bonjour tout le monde. Ceci est un test.');
});

test('fromSrt convertit les heures correctement', function () use ($service) {
    $srt = <<<'SRT'
    1
    01:02:03,456 --> 01:02:07,000
    Segment long.
    SRT;

    $resultat = $service->fromSrt($srt);
    $attendu = 1 * 3600 + 2 * 60 + 3 + 0.456;

    expect($resultat['segments'][0]['start'])->toBe($attendu);
});

test('fromSrt ignore les blocs sans timestamp valide', function () use ($service) {
    $srt = <<<'SRT'
    1
    TIMESTAMP INVALIDE
    Texte orphelin.

    2
    00:00:01,000 --> 00:00:02,000
    Texte valide.
    SRT;

    $resultat = $service->fromSrt($srt);

    expect($resultat['segments'])->toHaveCount(1)
        ->and($resultat['segments'][0]['text'])->toBe('Texte valide.');
});

test('fromSrt ignore les blocs trop courts', function () use ($service) {
    $srt = "1\n00:00:01,000 --> 00:00:02,000";

    $resultat = $service->fromSrt($srt);

    expect($resultat['segments'])->toBeEmpty();
});

test('fromSrt supprime les balises HTML du texte', function () use ($service) {
    $srt = <<<'SRT'
    1
    00:00:01,000 --> 00:00:03,000
    <i>Italique</i> et <b>gras</b>.
    SRT;

    $resultat = $service->fromSrt($srt);

    expect($resultat['segments'][0]['text'])->toBe('Italique et gras.');
});

test('fromSrt concatène le texte multi-ligne d\'un bloc', function () use ($service) {
    $srt = <<<'SRT'
    1
    00:00:01,000 --> 00:00:04,000
    Première ligne
    Deuxième ligne
    SRT;

    $resultat = $service->fromSrt($srt);

    expect($resultat['segments'][0]['text'])->toBe('Première ligne Deuxième ligne');
});

// ─── fromVtt() ─────────────────────────────────────────────────────────────────

test('fromVtt parse un fichier VTT valide', function () use ($service) {
    $vtt = <<<'VTT'
    WEBVTT

    00:00:01.000 --> 00:00:04.500
    Bonjour tout le monde.

    00:00:05.000 --> 00:00:08.000
    Ceci est un test.
    VTT;

    $resultat = $service->fromVtt($vtt);

    expect($resultat['segments'])->toHaveCount(2)
        ->and($resultat['segments'][0]['start'])->toBe(1.0)
        ->and($resultat['segments'][0]['end'])->toBe(4.5)
        ->and($resultat['segments'][0]['text'])->toBe('Bonjour tout le monde.')
        ->and($resultat['transcription'])->toBe('Bonjour tout le monde. Ceci est un test.');
});

test('fromVtt accepte le format sans heures (MM:SS.mmm)', function () use ($service) {
    $vtt = <<<'VTT'
    WEBVTT

    01:23.456 --> 01:27.000
    Court.
    VTT;

    $resultat = $service->fromVtt($vtt);

    expect($resultat['segments'][0]['start'])->toBe(1 * 60 + 23 + 0.456)
        ->and($resultat['segments'][0]['text'])->toBe('Court.');
});

test('fromVtt ignore les métadonnées de position', function () use ($service) {
    $vtt = <<<'VTT'
    WEBVTT

    00:00:01.000 --> 00:00:03.000 position:50% align:center
    Centré.
    VTT;

    $resultat = $service->fromVtt($vtt);

    expect($resultat['segments'])->toHaveCount(1)
        ->and($resultat['segments'][0]['text'])->toBe('Centré.');
});

test('fromVtt ignore les blocs sans texte', function () use ($service) {
    $vtt = <<<'VTT'
    WEBVTT

    00:00:01.000 --> 00:00:02.000

    00:00:03.000 --> 00:00:05.000
    Texte présent.
    VTT;

    $resultat = $service->fromVtt($vtt);

    expect($resultat['segments'])->toHaveCount(1)
        ->and($resultat['segments'][0]['text'])->toBe('Texte présent.');
});

test('fromVtt supprime les balises HTML du texte', function () use ($service) {
    $vtt = <<<'VTT'
    WEBVTT

    00:00:01.000 --> 00:00:03.000
    <v Intervenant>Bonjour.</v>
    VTT;

    $resultat = $service->fromVtt($vtt);

    expect($resultat['segments'][0]['text'])->toBe('Bonjour.');
});

// ─── detecterFormat() ──────────────────────────────────────────────────────────

test('detecterFormat retourne srt pour un fichier .srt', function () use ($service) {
    $fichier = UploadedFile::fake()->createWithContent('sous-titres.srt', '');

    expect($service->detecterFormat($fichier))->toBe('srt');
});

test('detecterFormat retourne vtt pour un fichier .vtt', function () use ($service) {
    $fichier = UploadedFile::fake()->createWithContent('sous-titres.vtt', '');

    expect($service->detecterFormat($fichier))->toBe('vtt');
});

test('detecterFormat retourne txt pour un fichier .txt', function () use ($service) {
    $fichier = UploadedFile::fake()->createWithContent('transcription.txt', '');

    expect($service->detecterFormat($fichier))->toBe('txt');
});

test('detecterFormat retourne txt pour une extension inconnue', function () use ($service) {
    $fichier = UploadedFile::fake()->createWithContent('document.doc', '');

    expect($service->detecterFormat($fichier))->toBe('txt');
});
