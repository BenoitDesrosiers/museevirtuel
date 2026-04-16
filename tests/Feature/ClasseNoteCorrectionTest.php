<?php

use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\GroupeNote;
use App\Models\GroupeNoteCorrection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Crée le contexte minimal : enseignant + cours + étudiant + section + groupe + note.
 *
 * @return array{enseignant: User, cours: Cours, etudiant: User, section: Classe, classe: Groupe, note: GroupeNote}
 */
function creerContexteCorrection(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);

    $cours = Cours::create([
        'nom_cours' => 'Cours test corrections',
        'description' => 'Test',
        'code' => '330-CORR',
        'groupe' => 'A',
        'enseignant_id' => $enseignant->id,
    ]);

    $etudiant = User::factory()->create(['role' => 'etudiant']);

    $section = Classe::create(['cours_id' => $cours->id]);
    $section->etudiants()->attach($etudiant->id);

    $classe = Groupe::create([
        'classe_id' => $section->id,
        'created_by' => $etudiant->id,
    ]);
    $classe->membres()->attach($etudiant->id);

    $note = GroupeNote::create([
        'groupe_id' => $classe->id,
        'user_id' => $etudiant->id,
        'contenu' => 'Voici le texte de la note étudiant.',
    ]);

    return compact('enseignant', 'cours', 'etudiant', 'section', 'classe', 'note');
}

// ─── upsertNoteCorrection() ────────────────────────────────────────────────────

test('upsertNoteCorrection() crée une correction et met à jour le HTML de la note', function () {
    $ctx = creerContexteCorrection();
    $commentaireId = '550e8400-e29b-41d4-a716-446655440000';
    $noteHtml = '<p>Voici le texte <mark data-comment-id="'.$commentaireId.'" class="comment-mark">de la</mark> note.</p>';

    $this->actingAs($ctx['enseignant'])
        ->put("/cours/{$ctx['cours']->id}/classes/{$ctx['section']->id}/groupes/{$ctx['classe']->id}/notes/{$ctx['note']->id}/corrections", [
            'commentaire_id' => $commentaireId,
            'contenu' => 'Mauvaise formulation — à corriger.',
            'note_html' => $noteHtml,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('groupe_note_corrections', [
        'note_id' => $ctx['note']->id,
        'commentaire_id' => $commentaireId,
        'contenu' => 'Mauvaise formulation — à corriger.',
        'user_id' => $ctx['enseignant']->id,
    ]);

    expect($ctx['note']->fresh()->contenu)->toBe($noteHtml);
});

test('upsertNoteCorrection() met à jour une correction existante sans doublon (upsert)', function () {
    $ctx = creerContexteCorrection();
    $commentaireId = '550e8400-e29b-41d4-a716-446655440001';

    GroupeNoteCorrection::create([
        'note_id' => $ctx['note']->id,
        'commentaire_id' => $commentaireId,
        'contenu' => 'Version initiale.',
        'user_id' => $ctx['enseignant']->id,
    ]);

    $this->actingAs($ctx['enseignant'])
        ->put("/cours/{$ctx['cours']->id}/classes/{$ctx['section']->id}/groupes/{$ctx['classe']->id}/notes/{$ctx['note']->id}/corrections", [
            'commentaire_id' => $commentaireId,
            'contenu' => 'Version corrigée.',
            'note_html' => '<p>html mis à jour</p>',
        ])
        ->assertRedirect();

    expect(
        GroupeNoteCorrection::where('note_id', $ctx['note']->id)
            ->where('commentaire_id', $commentaireId)
            ->count()
    )->toBe(1);

    $this->assertDatabaseHas('groupe_note_corrections', [
        'commentaire_id' => $commentaireId,
        'contenu' => 'Version corrigée.',
    ]);
});

test('upsertNoteCorrection() refuse un étudiant (redirigé — middleware rôle)', function () {
    $ctx = creerContexteCorrection();

    $this->actingAs($ctx['etudiant'])
        ->put("/cours/{$ctx['cours']->id}/classes/{$ctx['section']->id}/groupes/{$ctx['classe']->id}/notes/{$ctx['note']->id}/corrections", [
            'commentaire_id' => 'uuid-quelconque',
            'contenu' => 'Tentative étudiante.',
            'note_html' => '<p>html</p>',
        ])
        ->assertRedirect();

    $this->assertDatabaseMissing('groupe_note_corrections', ['note_id' => $ctx['note']->id]);
});

test("upsertNoteCorrection() refuse un enseignant d'un autre cours (403)", function () {
    $ctx = creerContexteCorrection();
    $autreEnseignant = User::factory()->create(['role' => 'enseignant']);

    $this->actingAs($autreEnseignant)
        ->put("/cours/{$ctx['cours']->id}/classes/{$ctx['section']->id}/groupes/{$ctx['classe']->id}/notes/{$ctx['note']->id}/corrections", [
            'commentaire_id' => 'uuid-quelconque',
            'contenu' => 'Correction non autorisée.',
            'note_html' => '<p>html</p>',
        ])
        ->assertForbidden();
});

test('upsertNoteCorrection() rejette une requête sans contenu (validation)', function () {
    $ctx = creerContexteCorrection();

    $this->actingAs($ctx['enseignant'])
        ->put("/cours/{$ctx['cours']->id}/classes/{$ctx['section']->id}/groupes/{$ctx['classe']->id}/notes/{$ctx['note']->id}/corrections", [
            'commentaire_id' => 'uuid-valide',
            'contenu' => '',
            'note_html' => '<p>html</p>',
        ])
        ->assertInvalid(['contenu']);
});

// ─── destroyNoteCorrection() ───────────────────────────────────────────────────

test('destroyNoteCorrection() supprime la correction et met à jour le HTML de la note', function () {
    $ctx = creerContexteCorrection();

    $correction = GroupeNoteCorrection::create([
        'note_id' => $ctx['note']->id,
        'commentaire_id' => '550e8400-e29b-41d4-a716-446655440002',
        'contenu' => 'À supprimer.',
        'user_id' => $ctx['enseignant']->id,
    ]);

    $htmlNettoyé = '<p>Voici le texte de la note étudiant.</p>';

    $this->actingAs($ctx['enseignant'])
        ->delete("/cours/{$ctx['cours']->id}/classes/{$ctx['section']->id}/groupes/{$ctx['classe']->id}/notes/{$ctx['note']->id}/corrections/{$correction->id}", [
            'note_html' => $htmlNettoyé,
        ])
        ->assertRedirect();

    $this->assertDatabaseMissing('groupe_note_corrections', ['id' => $correction->id]);
    expect($ctx['note']->fresh()->contenu)->toBe($htmlNettoyé);
});

test('destroyNoteCorrection() refuse un étudiant (redirigé — middleware rôle)', function () {
    $ctx = creerContexteCorrection();

    $correction = GroupeNoteCorrection::create([
        'note_id' => $ctx['note']->id,
        'commentaire_id' => '550e8400-e29b-41d4-a716-446655440003',
        'contenu' => 'Correction test.',
        'user_id' => $ctx['enseignant']->id,
    ]);

    $this->actingAs($ctx['etudiant'])
        ->delete("/cours/{$ctx['cours']->id}/classes/{$ctx['section']->id}/groupes/{$ctx['classe']->id}/notes/{$ctx['note']->id}/corrections/{$correction->id}", [
            'note_html' => '<p>html</p>',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('groupe_note_corrections', ['id' => $correction->id]);
});

test("destroyNoteCorrection() retourne 404 si la correction n'appartient pas à la note", function () {
    $ctx = creerContexteCorrection();

    $autreNote = GroupeNote::create([
        'groupe_id' => $ctx['classe']->id,
        'user_id' => $ctx['etudiant']->id,
        'contenu' => 'Autre note.',
    ]);

    $correctionDAutreNote = GroupeNoteCorrection::create([
        'note_id' => $autreNote->id,
        'commentaire_id' => '550e8400-e29b-41d4-a716-446655440004',
        'contenu' => 'Correction sur autre note.',
        'user_id' => $ctx['enseignant']->id,
    ]);

    $this->actingAs($ctx['enseignant'])
        ->delete("/cours/{$ctx['cours']->id}/classes/{$ctx['section']->id}/groupes/{$ctx['classe']->id}/notes/{$ctx['note']->id}/corrections/{$correctionDAutreNote->id}", [
            'note_html' => '<p>html</p>',
        ])
        ->assertNotFound();
});
