<?php

use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\GroupeMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Crée un scénario standard : enseignant, cours, classe, groupe, étudiant membre.
 *
 * @return array{enseignant: User, etudiant: User, cours: Cours, classe: Classe, groupe: Groupe}
 */
function creerScenarioMediaEdit(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $etudiant = User::factory()->create(['role' => 'etudiant']);

    $cours = Cours::create([
        'nom_cours' => 'Histoire du Québec',
        'description' => 'Test',
        'code' => '330-IMG',
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
 * Génère un fichier PNG de test (10×10 px rouge) et crée l'enregistrement GroupeMedia.
 * Retourne l'objet média et le chemin absolu du fichier créé.
 *
 * @return array{media: GroupeMedia, fullPath: string}
 */
function creerPhotoTest(Groupe $groupe, User $auteur, string $ext = 'png'): array
{
    $dir = public_path("images/groupes/{$groupe->id}");
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $filename = "test_{$auteur->id}_{$groupe->id}.{$ext}";
    $fullPath = "{$dir}/{$filename}";

    if ($ext === 'png') {
        // Crée une image PNG 10×10 rouge via GD.
        $img = imagecreatetruecolor(10, 10);
        $red = imagecolorallocate($img, 255, 0, 0);
        imagefill($img, 0, 0, $red);
        imagepng($img, $fullPath);
        imagedestroy($img);
    } elseif ($ext === 'gif') {
        // GIF minimal valide (1×1 pixel).
        file_put_contents($fullPath, base64_decode(
            'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'
        ));
    }

    $media = GroupeMedia::create([
        'groupe_id' => $groupe->id,
        'user_id' => $auteur->id,
        'nom_original' => "photo.{$ext}",
        'file_path' => "images/groupes/{$groupe->id}/{$filename}",
        'type' => 'photo',
        'taille' => filesize($fullPath),
    ]);

    return compact('media', 'fullPath');
}

// ─── rotate ───────────────────────────────────────────────────────────────────

test('un membre peut pivoter une photo de son groupe', function () {
    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe] = creerScenarioMediaEdit();
    ['media' => $media, 'fullPath' => $fullPath] = creerPhotoTest($groupe, $etudiant);

    $this->actingAs($etudiant)
        ->post(
            route('groupes.medias.editer', [$cours, $classe, $groupe, $media]),
            ['operation' => 'rotate', 'angle' => 90],
        )
        ->assertRedirect();

    // Le fichier existe toujours et la taille a été mise à jour en base.
    expect(file_exists($fullPath))->toBeTrue();
    $this->assertDatabaseHas('groupe_medias', ['id' => $media->id]);

    @unlink($fullPath);
});

test('l\'enseignant peut pivoter une photo du groupe', function () {
    ['enseignant' => $enseignant, 'etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe] = creerScenarioMediaEdit();
    ['media' => $media, 'fullPath' => $fullPath] = creerPhotoTest($groupe, $etudiant);

    $this->actingAs($enseignant)
        ->post(
            route('groupes.medias.editer', [$cours, $classe, $groupe, $media]),
            ['operation' => 'rotate', 'angle' => 180],
        )
        ->assertRedirect();

    @unlink($fullPath);
});

test('un coéquipier peut pivoter la photo d\'un autre membre', function () {
    ['cours' => $cours, 'classe' => $classe, 'groupe' => $groupe, 'etudiant' => $auteur] = creerScenarioMediaEdit();

    // Deuxième membre du même groupe.
    $coequipier = User::factory()->create(['role' => 'etudiant']);
    $classe->etudiants()->attach($coequipier->id);
    $groupe->membres()->attach($coequipier->id);

    ['media' => $media, 'fullPath' => $fullPath] = creerPhotoTest($groupe, $auteur);

    $this->actingAs($coequipier)
        ->post(
            route('groupes.medias.editer', [$cours, $classe, $groupe, $media]),
            ['operation' => 'rotate', 'angle' => 90],
        )
        ->assertRedirect();

    @unlink($fullPath);
});

// ─── flip ─────────────────────────────────────────────────────────────────────

test('un membre peut retourner une photo horizontalement', function () {
    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe] = creerScenarioMediaEdit();
    ['media' => $media, 'fullPath' => $fullPath] = creerPhotoTest($groupe, $etudiant);

    $this->actingAs($etudiant)
        ->post(
            route('groupes.medias.editer', [$cours, $classe, $groupe, $media]),
            ['operation' => 'flip', 'direction' => 'horizontal'],
        )
        ->assertRedirect();

    @unlink($fullPath);
});

test('un membre peut retourner une photo verticalement', function () {
    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe] = creerScenarioMediaEdit();
    ['media' => $media, 'fullPath' => $fullPath] = creerPhotoTest($groupe, $etudiant);

    $this->actingAs($etudiant)
        ->post(
            route('groupes.medias.editer', [$cours, $classe, $groupe, $media]),
            ['operation' => 'flip', 'direction' => 'vertical'],
        )
        ->assertRedirect();

    @unlink($fullPath);
});

// ─── crop ─────────────────────────────────────────────────────────────────────

test('un membre peut rogner une photo', function () {
    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe] = creerScenarioMediaEdit();
    ['media' => $media, 'fullPath' => $fullPath] = creerPhotoTest($groupe, $etudiant);

    $tailleAvant = filesize($fullPath);

    $this->actingAs($etudiant)
        ->post(
            route('groupes.medias.editer', [$cours, $classe, $groupe, $media]),
            ['operation' => 'crop', 'x' => 0, 'y' => 0, 'width' => 5, 'height' => 5],
        )
        ->assertRedirect();

    expect(file_exists($fullPath))->toBeTrue();

    // La taille en base a été rafraîchie.
    $media->refresh();
    expect($media->taille)->toBe(filesize($fullPath));

    @unlink($fullPath);
});

// ─── Contrôle d'accès ─────────────────────────────────────────────────────────

test('un non-membre ne peut pas éditer une photo', function () {
    ['cours' => $cours, 'classe' => $classe, 'groupe' => $groupe, 'etudiant' => $etudiant] = creerScenarioMediaEdit();
    ['media' => $media, 'fullPath' => $fullPath] = creerPhotoTest($groupe, $etudiant);

    $etranger = User::factory()->create(['role' => 'etudiant']);

    $this->actingAs($etranger)
        ->post(
            route('groupes.medias.editer', [$cours, $classe, $groupe, $media]),
            ['operation' => 'rotate', 'angle' => 90],
        )
        ->assertForbidden();

    @unlink($fullPath);
});

// ─── Validation ───────────────────────────────────────────────────────────────

test('un gif est refusé avec 422', function () {
    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe] = creerScenarioMediaEdit();
    ['media' => $media, 'fullPath' => $fullPath] = creerPhotoTest($groupe, $etudiant, 'gif');

    $this->actingAs($etudiant)
        ->post(
            route('groupes.medias.editer', [$cours, $classe, $groupe, $media]),
            ['operation' => 'rotate', 'angle' => 90],
        )
        ->assertStatus(422);

    @unlink($fullPath);
});

test('un angle invalide est refusé', function () {
    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe] = creerScenarioMediaEdit();
    ['media' => $media, 'fullPath' => $fullPath] = creerPhotoTest($groupe, $etudiant);

    $this->actingAs($etudiant)
        ->post(
            route('groupes.medias.editer', [$cours, $classe, $groupe, $media]),
            ['operation' => 'rotate', 'angle' => 45], // 45° non autorisé
        )
        ->assertSessionHasErrors('angle');

    @unlink($fullPath);
});

test('une direction de flip invalide est refusée', function () {
    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe] = creerScenarioMediaEdit();
    ['media' => $media, 'fullPath' => $fullPath] = creerPhotoTest($groupe, $etudiant);

    $this->actingAs($etudiant)
        ->post(
            route('groupes.medias.editer', [$cours, $classe, $groupe, $media]),
            ['operation' => 'flip', 'direction' => 'diagonal'], // invalide
        )
        ->assertSessionHasErrors('direction');

    @unlink($fullPath);
});

test('une opération inconnue est refusée', function () {
    ['etudiant' => $etudiant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe] = creerScenarioMediaEdit();
    ['media' => $media, 'fullPath' => $fullPath] = creerPhotoTest($groupe, $etudiant);

    $this->actingAs($etudiant)
        ->post(
            route('groupes.medias.editer', [$cours, $classe, $groupe, $media]),
            ['operation' => 'sharpen'], // non supporté
        )
        ->assertSessionHasErrors('operation');

    @unlink($fullPath);
});
