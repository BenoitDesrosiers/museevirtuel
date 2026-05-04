<?php

use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetRecherche;
use App\Models\ProjetSectionMedia;
use App\Models\TypeProjet;
use App\Models\TypeProjetSection;
use App\Models\User;
use Illuminate\Http\UploadedFile;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Crée un scénario complet : enseignant, cours, classe, groupe avec étudiant,
 * typeProjet avec une section video, et un projet associé.
 *
 * @return array{enseignant: User, etudiant: User, cours: Cours, classeSection: Classe, groupe: Groupe, typeProjet: TypeProjet, section: TypeProjetSection, projet: ProjetRecherche}
 */
function creerScenarioMedia(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $etudiant = User::factory()->create(['role' => 'etudiant']);

    $cours = Cours::create([
        'nom_cours' => 'Histoire',
        'description' => 'Test',
        'code' => '330-MED',
        'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);

    $classeSection = Classe::create(['cours_id' => $cours->id]);
    $classeSection->etudiants()->attach($etudiant->id);

    $groupe = Groupe::create(['classe_id' => $classeSection->id, 'created_by' => $etudiant->id]);
    $groupe->membres()->attach($etudiant->id);

    $typeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id,
        'nom' => 'Projet vidéo',
        'accessible' => true,
    ]);

    $section = TypeProjetSection::create([
        'type_projet_id' => $typeProjet->id,
        'label' => 'Vidéo',
        'type' => 'video',
        'ordre' => 1,
    ]);

    $projet = ProjetRecherche::create([
        'groupe_id' => $groupe->id,
        'type_projet_id' => $typeProjet->id,
    ]);

    return compact('enseignant', 'etudiant', 'cours', 'classeSection', 'groupe', 'typeProjet', 'section', 'projet');
}

/**
 * Retourne l'URL de base pour les routes médias de section.
 */
function urlMedias(Cours $cours, Classe $classeSection, Groupe $groupe, TypeProjet $typeProjet, TypeProjetSection $section): string
{
    return "/cours/{$cours->id}/classes/{$classeSection->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/sections/{$section->id}/medias";
}

// ─── store() — URL ────────────────────────────────────────────────────────────

test('un étudiant membre peut attacher une URL vidéo à une section', function () {
    $s = creerScenarioMedia();

    $this->actingAs($s['etudiant'])
        ->post(urlMedias($s['cours'], $s['classeSection'], $s['groupe'], $s['typeProjet'], $s['section']), [
            'source_type' => 'url',
            'url' => 'https://www.youtube.com/watch?v=example',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('projet_section_medias', [
        'projet_id' => $s['projet']->id,
        'section_id' => $s['section']->id,
        'source_type' => 'url',
        'url' => 'https://www.youtube.com/watch?v=example',
        'type' => 'video',
        'user_id' => $s['etudiant']->id,
    ]);
});

// ─── store() — Upload ─────────────────────────────────────────────────────────

test('un étudiant membre peut uploader un fichier vidéo dans une section', function () {
    $s = creerScenarioMedia();

    // Créer le dossier public cible pour éviter l'erreur mkdir
    $dir = public_path("medias/projets/{$s['projet']->id}/sections/{$s['section']->id}");
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $fakeFile = UploadedFile::fake()->create('video.mp4', 1024, 'video/mp4');

    $this->actingAs($s['etudiant'])
        ->post(urlMedias($s['cours'], $s['classeSection'], $s['groupe'], $s['typeProjet'], $s['section']), [
            'source_type' => 'upload',
            'fichier' => $fakeFile,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('projet_section_medias', [
        'projet_id' => $s['projet']->id,
        'section_id' => $s['section']->id,
        'source_type' => 'upload',
        'type' => 'video',
        'user_id' => $s['etudiant']->id,
    ]);
});

// ─── store() — Accès refusé ────────────────────────────────────────────────────

test('un étudiant non-membre ne peut pas ajouter un média', function () {
    $s = creerScenarioMedia();
    $intrus = User::factory()->create(['role' => 'etudiant']);

    $this->actingAs($intrus)
        ->post(urlMedias($s['cours'], $s['classeSection'], $s['groupe'], $s['typeProjet'], $s['section']), [
            'source_type' => 'url',
            'url' => 'https://example.com/video.mp4',
        ])
        ->assertForbidden();
});

test('une section non-vidéo/audio retourne 422', function () {
    $s = creerScenarioMedia();

    // Créer une section de type texte
    $sectionTexte = TypeProjetSection::create([
        'type_projet_id' => $s['typeProjet']->id,
        'label' => 'Introduction',
        'type' => 'texte',
        'ordre' => 2,
    ]);

    $this->actingAs($s['etudiant'])
        ->post(urlMedias($s['cours'], $s['classeSection'], $s['groupe'], $s['typeProjet'], $sectionTexte), [
            'source_type' => 'url',
            'url' => 'https://example.com/video.mp4',
        ])
        ->assertStatus(422);
});

// ─── destroy() ────────────────────────────────────────────────────────────────

test("l'auteur d'un média peut le supprimer", function () {
    $s = creerScenarioMedia();

    $media = ProjetSectionMedia::create([
        'projet_id' => $s['projet']->id,
        'section_id' => $s['section']->id,
        'type' => 'video',
        'source_type' => 'url',
        'url' => 'https://example.com/video.mp4',
        'user_id' => $s['etudiant']->id,
    ]);

    $this->actingAs($s['etudiant'])
        ->delete(urlMedias($s['cours'], $s['classeSection'], $s['groupe'], $s['typeProjet'], $s['section'])."/{$media->id}")
        ->assertRedirect();

    $this->assertDatabaseMissing('projet_section_medias', ['id' => $media->id]);
});

test("l'enseignant peut supprimer un média d'un groupe", function () {
    $s = creerScenarioMedia();

    $media = ProjetSectionMedia::create([
        'projet_id' => $s['projet']->id,
        'section_id' => $s['section']->id,
        'type' => 'video',
        'source_type' => 'url',
        'url' => 'https://example.com/video.mp4',
        'user_id' => $s['etudiant']->id,
    ]);

    $this->actingAs($s['enseignant'])
        ->delete(urlMedias($s['cours'], $s['classeSection'], $s['groupe'], $s['typeProjet'], $s['section'])."/{$media->id}")
        ->assertRedirect();

    $this->assertDatabaseMissing('projet_section_medias', ['id' => $media->id]);
});

test("un étudiant tiers ne peut pas supprimer le média d'un autre", function () {
    $s = creerScenarioMedia();
    $intrus = User::factory()->create(['role' => 'etudiant']);

    $media = ProjetSectionMedia::create([
        'projet_id' => $s['projet']->id,
        'section_id' => $s['section']->id,
        'type' => 'video',
        'source_type' => 'url',
        'url' => 'https://example.com/video.mp4',
        'user_id' => $s['etudiant']->id,
    ]);

    $this->actingAs($intrus)
        ->delete(urlMedias($s['cours'], $s['classeSection'], $s['groupe'], $s['typeProjet'], $s['section'])."/{$media->id}")
        ->assertForbidden();
});
