<?php

use App\Actions\StoreUploadedFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('executePrive range le fichier sur le disque privé avec un nom UUID', function () {
    Storage::fake('local');

    $meta = app(StoreUploadedFile::class)->executePrive(UploadedFile::fake()->create('Clip.MP4', 10), 'medias/test');

    expect($meta['nom_original'])->toBe('Clip.MP4')
        ->and($meta['file_path'])->toStartWith('medias/test/')->toEndWith('.mp4');
    Storage::disk('local')->assertExists($meta['file_path']);
});

test('executePrive avec un nom fixe remplace le fichier précédent', function () {
    Storage::fake('local');
    $action = app(StoreUploadedFile::class);

    $action->executePrive(UploadedFile::fake()->createWithContent('a.mp4', 'v1'), 'visio', 'recording');
    $meta = $action->executePrive(UploadedFile::fake()->createWithContent('b.mp4', 'v2'), 'visio', 'recording');

    expect($meta['file_path'])->toBe('visio/recording.mp4')
        ->and(Storage::disk('local')->get('visio/recording.mp4'))->toBe('v2');
});
