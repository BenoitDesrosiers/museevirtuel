<?php

use App\Models\Classe;
use App\Models\ConsentementVideo;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use App\Models\User;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Crée un scénario : enseignant, cours, classe, groupe avec étudiant et typeProjet.
 *
 * @return array{enseignant: User, etudiant: User, cours: Cours, classeSection: Classe, groupe: Groupe, typeProjet: TypeProjet, projet: ProjetRecherche}
 */
function creerScenarioConsentement(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $etudiant = User::factory()->create(['role' => 'etudiant']);

    $cours = Cours::create([
        'nom_cours' => 'Histoire',
        'description' => 'Test',
        'code' => '330-CON',
        'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);

    $classeSection = Classe::create(['cours_id' => $cours->id]);
    $classeSection->etudiants()->attach($etudiant->id);

    $groupe = Groupe::create(['classe_id' => $classeSection->id, 'created_by' => $etudiant->id]);
    $groupe->membres()->attach($etudiant->id);

    $typeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id,
        'nom' => 'Entrevue filmée',
        'accessible' => true,
    ]);

    $projet = ProjetRecherche::create([
        'groupe_id' => $groupe->id,
        'type_projet_id' => $typeProjet->id,
    ]);

    return compact('enseignant', 'etudiant', 'cours', 'classeSection', 'groupe', 'typeProjet', 'projet');
}

/**
 * Retourne l'URL de consentement pour un projet.
 */
function urlConsentement(Cours $cours, Groupe $groupe, TypeProjet $typeProjet): string
{
    return "/cours/{$cours->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/consentement";
}

// ─── store() — Étudiant ────────────────────────────────────────────────────────

test('un étudiant membre peut enregistrer son consentement', function () {
    $s = creerScenarioConsentement();

    $this->actingAs($s['etudiant'])
        ->post(urlConsentement($s['cours'], $s['groupe'], $s['typeProjet']), [
            'accepte' => true,
            'signature' => 'data:image/png;base64,iVBORw0KGgo=',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('consentement_videos', [
        'user_id' => $s['etudiant']->id,
        'projet_id' => $s['projet']->id,
        'type' => 'etudiant',
        'accepte' => true,
    ]);
});

test("le consentement est mis à jour si l'étudiant soumet à nouveau", function () {
    $s = creerScenarioConsentement();

    // Premier consentement
    $this->actingAs($s['etudiant'])
        ->post(urlConsentement($s['cours'], $s['groupe'], $s['typeProjet']), [
            'accepte' => true,
            'signature' => 'data:image/png;base64,iVBORw0KGgo=',
        ]);

    // Révocation
    $this->actingAs($s['etudiant'])
        ->post(urlConsentement($s['cours'], $s['groupe'], $s['typeProjet']), [
            'accepte' => false,
        ]);

    $this->assertDatabaseCount('consentement_videos', 1);
    $this->assertDatabaseHas('consentement_videos', [
        'user_id' => $s['etudiant']->id,
        'projet_id' => $s['projet']->id,
        'accepte' => false,
    ]);
});

// ─── store() — Personne âgée ───────────────────────────────────────────────────

test('une personne âgée assignée au groupe peut enregistrer son consentement', function () {
    $s = creerScenarioConsentement();

    $personneAgee = User::factory()->create(['role' => 'personne_agee', 'statut' => 'actif']);
    $s['groupe']->update(['personne_agee_id' => $personneAgee->id]);

    $this->actingAs($personneAgee)
        ->post(urlConsentement($s['cours'], $s['groupe'], $s['typeProjet']), [
            'accepte' => true,
            'signature' => 'data:image/png;base64,iVBORw0KGgo=',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('consentement_videos', [
        'user_id' => $personneAgee->id,
        'projet_id' => $s['projet']->id,
        'type' => 'personne_agee',
        'accepte' => true,
    ]);
});

// ─── store() — Accès refusé ────────────────────────────────────────────────────

test('un utilisateur non membre du groupe ne peut pas enregistrer de consentement', function () {
    $s = creerScenarioConsentement();
    $intrus = User::factory()->create(['role' => 'etudiant']);

    $this->actingAs($intrus)
        ->post(urlConsentement($s['cours'], $s['groupe'], $s['typeProjet']), [
            'accepte' => true,
        ])
        ->assertForbidden();
});

test('signed_at est rempli quand accepte est vrai, null sinon', function () {
    $s = creerScenarioConsentement();

    $this->actingAs($s['etudiant'])
        ->post(urlConsentement($s['cours'], $s['groupe'], $s['typeProjet']), [
            'accepte' => true,
        ]);

    $consentement = ConsentementVideo::where('user_id', $s['etudiant']->id)->first();
    expect($consentement->signed_at)->not->toBeNull();

    // Révocation — signed_at devient null
    $this->actingAs($s['etudiant'])
        ->post(urlConsentement($s['cours'], $s['groupe'], $s['typeProjet']), [
            'accepte' => false,
        ]);

    $consentement->refresh();
    expect($consentement->signed_at)->toBeNull();
});
