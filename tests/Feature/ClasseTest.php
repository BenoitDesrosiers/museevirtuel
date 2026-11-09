<?php

use App\Models\Classe;
use App\Models\Cours;
use App\Models\CoursObjectif;
use App\Models\CoursReference;
use App\Models\EcheancierEtape;
use App\Models\EcheancierEtudiantProgress;
use App\Models\Groupe;
use App\Models\TypeProjet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function creerCoursAvecEnseignant(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = Cours::create([
        'nom_cours' => 'Histoire du Quebec',
        'description' => 'Cours test',
        'code' => '330-TEST',
        'groupe' => 'A',
        'enseignant_id' => $enseignant->id,
    ]);

    return compact('enseignant', 'cours');
}

function creerClassePourCours(Cours $cours, string $code): Classe
{
    return Classe::forceCreate([
        'id' => $cours->id,
        'cours_id' => $cours->id,
        'numero' => '00001',
        'code' => $cours->code,
        'nom' => "Classe {$code}",
    ]);
}

test('store cree une section pour le cours', function () {
    ['enseignant' => $enseignant, 'cours' => $cours] = creerCoursAvecEnseignant();

    $this->actingAs($enseignant)
        ->post("/cours/{$cours->id}/classes", [
            'numero' => '00001',
            'nom' => 'Classe 00001',
            'jour_semaine' => 'Lundi',
            'plage_horaire' => '08:30 - 11:30',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('classes', [
        'cours_id' => $cours->id,
        'numero' => '00001',
        'code' => $cours->code,
        'nom' => 'Classe 00001',
    ]);
});

test('store refuse un numero manquant avec message en francais', function () {
    ['enseignant' => $enseignant, 'cours' => $cours] = creerCoursAvecEnseignant();
    $enseignant->update(['locale' => 'fr']);

    $this->actingAs($enseignant)
        ->from("/cours/{$cours->id}")
        ->post("/cours/{$cours->id}/classes", [
            'nom' => 'Classe sans numéro',
        ])
        ->assertSessionHasErrors(['numero'])
        ->assertSessionHasErrors([
            'numero' => 'Le numéro de classe est obligatoire.',
        ]);
});

test('store refuse un numero qui n a pas 5 chiffres', function () {
    ['enseignant' => $enseignant, 'cours' => $cours] = creerCoursAvecEnseignant();
    $enseignant->update(['locale' => 'fr']);

    $this->actingAs($enseignant)
        ->from("/cours/{$cours->id}")
        ->post("/cours/{$cours->id}/classes", [
            'numero' => '123',
        ])
        ->assertSessionHasErrors(['numero'])
        ->assertSessionHasErrors([
            'numero' => 'Les 5 chiffres du groupe doivent être présents.',
        ]);
});

test('store refuse un etudiant non autorise', function () {
    ['cours' => $cours] = creerCoursAvecEnseignant();
    $etudiant = User::factory()->create(['role' => 'etudiant']);

    $this->actingAs($etudiant)
        ->post("/cours/{$cours->id}/classes", [])
        ->assertRedirect();
});

test('show retourne 404 quand la classe ne correspond pas au cours', function () {
    ['enseignant' => $enseignant, 'cours' => $cours] = creerCoursAvecEnseignant();
    ['cours' => $autreCours] = creerCoursAvecEnseignant();

    $classe = creerClassePourCours($cours, '402');

    $this->actingAs($enseignant)
        ->get("/cours/{$autreCours->id}/classes/{$classe->id}")
        ->assertNotFound();
});

test('show est accessible a un etudiant inscrit dans la section', function () {
    ['cours' => $cours] = creerCoursAvecEnseignant();
    $etudiant = User::factory()->create(['role' => 'etudiant']);

    $classe = creerClassePourCours($cours, '403');
    $classe->etudiants()->attach($etudiant->id);

    $this->actingAs($etudiant)
        ->get("/cours/{$cours->id}/classes/{$classe->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Classes/Show')
            ->where('utilisateurConnecteId', $etudiant->id)
        );
});

test('destroy supprime la section pour l enseignant du cours', function () {
    ['enseignant' => $enseignant, 'cours' => $cours] = creerCoursAvecEnseignant();
    $classe = creerClassePourCours($cours, '404');

    $this->actingAs($enseignant)
        ->delete("/cours/{$cours->id}/classes/{$classe->id}")
        ->assertRedirect();

    $this->assertDatabaseMissing('classes', ['id' => $classe->id]);
});

test("show donne les types de projets à l'enseignant, sans les ressources étudiantes", function () {
    ['enseignant' => $enseignant, 'cours' => $cours] = creerCoursAvecEnseignant();
    $classe = creerClassePourCours($cours, '405');
    $typeProjet = TypeProjet::create(['enseignant_id' => $enseignant->id, 'cours_id' => $cours->id, 'nom' => 'TP', 'ponderation' => 25, 'is_sommatif' => true]);
    CoursObjectif::create(['cours_id' => $cours->id, 'contenu' => 'Objectif', 'ordre' => 1]);

    $this->actingAs($enseignant)
        ->get("/cours/{$cours->id}/classes/{$classe->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Classes/Show')
            ->where('cours', $cours->only('id', 'nom_cours', 'code', 'groupe'))
            ->where('classe.id', $classe->id)
            ->where('estEnseignant', true)
            ->has('typesProjets', 1)
            ->where('typesProjets.0.id', $typeProjet->id)
            ->where('typesProjets.0.nom', 'TP')
            ->where('echeancierEtapes', [])
            ->where('documents', [])
            ->where('objectifs', [])
            ->where('references', [])
        );
});

test("show donne à l'étudiant l'échéancier avec sa progression, les objectifs et les références", function () {
    ['enseignant' => $enseignant, 'cours' => $cours] = creerCoursAvecEnseignant();
    $etudiant = User::factory()->create(['role' => 'etudiant']);
    $classe = creerClassePourCours($cours, '406');
    $classe->etudiants()->attach($etudiant->id);
    TypeProjet::create(['enseignant_id' => $enseignant->id, 'cours_id' => $cours->id, 'nom' => 'TP']);

    $faite = EcheancierEtape::create(['cours_id' => $cours->id, 'semaine' => 1, 'etape' => 'Lire', 'is_done' => false, 'ordre' => 1]);
    $aFaire = EcheancierEtape::create(['cours_id' => $cours->id, 'semaine' => 2, 'etape' => 'Écrire', 'is_done' => true, 'ordre' => 1]);
    EcheancierEtudiantProgress::create(['echeancier_etape_id' => $faite->id, 'user_id' => $etudiant->id, 'is_done' => true]);
    $objectif = CoursObjectif::create(['cours_id' => $cours->id, 'contenu' => 'Comprendre', 'ordre' => 1]);
    $reference = CoursReference::create(['cours_id' => $cours->id, 'nom' => 'BAnQ', 'url' => 'https://banq.qc.ca', 'ordre' => 1]);

    $this->actingAs($etudiant)
        ->get("/cours/{$cours->id}/classes/{$classe->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('estEnseignant', false)
            ->where('typesProjets', [])
            ->where('echeancierEtapes', [
                ['id' => $faite->id, 'semaine' => 1, 'etape' => 'Lire', 'is_done' => false, 'ordre' => 1, 'etudiant_done' => true],
                ['id' => $aFaire->id, 'semaine' => 2, 'etape' => 'Écrire', 'is_done' => true, 'ordre' => 1, 'etudiant_done' => false],
            ])
            ->where('documents', [])
            ->where('objectifs', [['id' => $objectif->id, 'contenu' => 'Comprendre', 'ordre' => 1]])
            ->where('references', [['id' => $reference->id, 'nom' => 'BAnQ', 'url' => 'https://banq.qc.ca', 'ordre' => 1]])
        );
});

// ─── Fuite de données — props de Classes/Show ─────────────────────────────────

/**
 * Classe avec deux groupes : celui de l'étudiant (avec un témoin) et un autre groupe.
 *
 * @return array<string, mixed>
 */
function scenarioClasseConfidentialite(): array
{
    ['enseignant' => $enseignant, 'cours' => $cours] = creerCoursAvecEnseignant();
    $classe = creerClassePourCours($cours, '501');
    $etudiant = User::factory()->create(['role' => 'etudiant', 'no_da' => '1110001']);
    $autre = User::factory()->create(['role' => 'etudiant', 'no_da' => '1110002']);
    $temoin = User::factory()->create(['role' => 'personne_agee', 'signature_electronique' => 'Signature secrète']);
    $classe->etudiants()->attach([$etudiant->id => ['statut_cours' => 'actif'], $autre->id => ['statut_cours' => 'actif']]);

    $monGroupe = Groupe::create(['classe_id' => $classe->id, 'created_by' => $etudiant->id, 'personne_agee_id' => $temoin->id]);
    $monGroupe->membres()->attach($etudiant->id);
    $autreGroupe = Groupe::create(['classe_id' => $classe->id, 'created_by' => $autre->id]);
    $autreGroupe->membres()->attach($autre->id);

    return compact('enseignant', 'cours', 'classe', 'etudiant', 'autre', 'temoin', 'monGroupe', 'autreGroupe');
}

test("show n'envoie à l'étudiant ni la liste des inscrits, ni les autres groupes, ni les données personnelles", function () {
    $s = scenarioClasseConfidentialite();

    $reponse = $this->actingAs($s['etudiant'])->get("/cours/{$s['cours']->id}/classes/{$s['classe']->id}");

    $reponse->assertOk()->assertInertia(fn ($page) => $page
        ->where('classe.etudiants', [])
        ->has('classe.groupes', 1)
        ->where('classe.groupes.0.id', $s['monGroupe']->id)
        ->where('classe.groupes.0.membres.0', $s['etudiant']->only('id', 'prenom', 'nom'))
        ->where('classe.groupes.0.temoin', $s['temoin']->only('id', 'prenom', 'nom'))
    );
    $props = json_encode($reponse->viewData('page')['props']);
    expect($props)->not->toContain($s['autre']->email)
        ->not->toContain('1110002')
        ->not->toContain($s['temoin']->email)
        ->not->toContain('Signature secrète');
});

test("show envoie à l'enseignant les inscrits et tous les groupes, sans le reste des comptes", function () {
    $s = scenarioClasseConfidentialite();

    $reponse = $this->actingAs($s['enseignant'])->get("/cours/{$s['cours']->id}/classes/{$s['classe']->id}");

    $reponse->assertOk()->assertInertia(fn ($page) => $page
        ->has('classe.etudiants', 2)
        ->where('classe.etudiants.0', [
            ...$s['etudiant']->only('id', 'prenom', 'nom', 'email', 'no_da'),
            'pivot' => ['statut_cours' => 'actif'],
        ])
        ->has('classe.groupes', 2)
        ->where('classe.groupes.0.numero', 1)
        ->where('classe.groupes.1.temoin', null)
    );
    expect(json_encode($reponse->viewData('page')['props']))->not->toContain('Signature secrète');
});

test("show donne à l'admin la même vue que l'enseignant, avec la liste des inscrits", function () {
    $s = scenarioClasseConfidentialite();
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get("/cours/{$s['cours']->id}/classes/{$s['classe']->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('estEnseignant', true)
            ->has('classe.etudiants', 2)
            ->where('classe.etudiants.0.no_da', '1110001')
            ->has('classe.groupes', 2)
        );
});
