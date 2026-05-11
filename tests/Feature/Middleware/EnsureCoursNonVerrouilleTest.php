<?php

use App\Models\Classe;
use App\Models\Cours;
use App\Models\User;

beforeEach(function () {
    $this->withoutVite();

    $this->enseignant = User::factory()->create(['role' => 'enseignant']);
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->etudiant = User::factory()->create(['role' => 'etudiant']);

    $this->cours = Cours::create([
        'nom_cours' => 'Cours accessible',
        'code' => 'TEST-101',
        'groupe' => '01',
        'enseignant_id' => $this->enseignant->id,
        'is_verrouille' => false,
    ]);

    $this->coursVerrouille = Cours::create([
        'nom_cours' => 'Cours verrouillé',
        'code' => 'TEST-102',
        'groupe' => '01',
        'enseignant_id' => $this->enseignant->id,
        'is_verrouille' => true,
    ]);

    // Une classe associée au cours verrouillé (nécessaire pour les routes avec {classe})
    $this->classe = Classe::create([
        'cours_id' => $this->coursVerrouille->id,
        'code' => 'CLS-01',
        'numero' => '01',
    ]);
});

// ─── Routes etudiant-only ─────────────────────────────────────────────────────

it('affiche la page verrouillée à un étudiant sur la liste des classes d\'un cours verrouillé', function () {
    $this->actingAs($this->etudiant)
        ->get(route('classes.index', $this->coursVerrouille))
        ->assertStatus(403)
        ->assertInertia(fn ($page) => $page->component('Cours/Verrouille'));
});

it('laisse passer un étudiant sur la liste des classes d\'un cours non verrouillé', function () {
    $this->actingAs($this->etudiant)
        ->get(route('classes.index', $this->cours))
        ->assertSuccessful();
});

// ─── Routes mixtes etudiant + enseignant + admin ──────────────────────────────

it('affiche la page verrouillée à un étudiant sur le détail d\'une classe d\'un cours verrouillé', function () {
    $this->actingAs($this->etudiant)
        ->get(route('classes.show', [$this->coursVerrouille, $this->classe]))
        ->assertStatus(403)
        ->assertInertia(fn ($page) => $page->component('Cours/Verrouille'));
});

it('laisse passer l\'enseignant propriétaire sur le détail d\'une classe verrouillée', function () {
    $this->actingAs($this->enseignant)
        ->get(route('classes.show', [$this->coursVerrouille, $this->classe]))
        ->assertSuccessful();
});

it('laisse passer un admin sur le détail d\'une classe d\'un cours verrouillé', function () {
    $this->actingAs($this->admin)
        ->get(route('classes.show', [$this->coursVerrouille, $this->classe]))
        ->assertSuccessful();
});

it('affiche la page verrouillée à un étudiant sur la liste des groupes d\'un cours verrouillé', function () {
    $this->actingAs($this->etudiant)
        ->get(route('groupes.index', [$this->coursVerrouille, $this->classe]))
        ->assertStatus(403)
        ->assertInertia(fn ($page) => $page->component('Cours/Verrouille'));
});

// ─── Enseignant d'un autre cours ne peut pas passer ───────────────────────────

it('affiche la page verrouillée à un enseignant qui ne possède pas le cours verrouillé', function () {
    $autreEnseignant = User::factory()->create(['role' => 'enseignant']);

    $this->actingAs($autreEnseignant)
        ->get(route('classes.show', [$this->coursVerrouille, $this->classe]))
        ->assertStatus(403)
        ->assertInertia(fn ($page) => $page->component('Cours/Verrouille'));
});

// ─── Données passées à la page ────────────────────────────────────────────────

it('passe les données du cours à la page verrouillée', function () {
    $this->actingAs($this->etudiant)
        ->get(route('classes.show', [$this->coursVerrouille, $this->classe]))
        ->assertInertia(fn ($page) => $page
            ->component('Cours/Verrouille')
            ->has('cours')
            ->where('cours.id', $this->coursVerrouille->id)
            ->where('cours.code', 'TEST-102')
        );
});
