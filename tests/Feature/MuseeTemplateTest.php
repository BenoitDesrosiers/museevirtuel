<?php

use App\Models\Cours;
use App\Models\MuseeTemplate;
use App\Models\TypeProjet;
use App\Models\TypeProjetSection;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Crée un cours minimal pour l'enseignant donné.
 */
function creerCoursMusee(User $enseignant): Cours
{
    return Cours::create([
        'nom_cours' => 'Cours musée test',
        'code' => '330-M1',
        'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);
}

/**
 * Crée un TypeProjet de type musée et retourne le couple [cours, typeProjet].
 *
 * @return array{Cours, TypeProjet}
 */
function creerTypeProjetMusee(User $enseignant): array
{
    $cours = creerCoursMusee($enseignant);
    $typeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id,
        'cours_id' => $cours->id,
        'nom' => 'Mon musée',
        'type' => 'musee',
        'accessible' => false,
    ]);

    return [$cours, $typeProjet];
}

// ─── Observer — création automatique du MuseeTemplate ─────────────────────────

test("l'observer crée un MuseeTemplate automatiquement lors de la création d'un TypeProjet musée", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    [, $typeProjet] = creerTypeProjetMusee($enseignant);

    expect(MuseeTemplate::where('type_projet_id', $typeProjet->id)->exists())->toBeTrue();
});

test("l'observer ne crée pas de MuseeTemplate pour un TypeProjet standard", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = creerCoursMusee($enseignant);

    $typeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id,
        'cours_id' => $cours->id,
        'nom' => 'Projet standard',
        'type' => 'standard',
        'accessible' => false,
    ]);

    expect(MuseeTemplate::where('type_projet_id', $typeProjet->id)->exists())->toBeFalse();
});

test('le MuseeTemplate créé automatiquement a les valeurs par défaut attendues', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    [, $typeProjet] = creerTypeProjetMusee($enseignant);

    $template = MuseeTemplate::where('type_projet_id', $typeProjet->id)->first();

    expect($template->font_titre_page)->toBe('Georgia');
    expect($template->font_corps)->toBe('Arial');
    expect($template->couleur_fond)->toBe('#F5EFE0');
    expect($template->couleur_titre)->toBe('#1A2744');
    expect($template->theme)->toBe('clair');
});

// ─── TypeProjetController::store — type musée ─────────────────────────────────

test("la création d'un TypeProjet de type musée redirige vers le template", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = creerCoursMusee($enseignant);

    $response = $this->actingAs($enseignant)
        ->post("/cours/{$cours->id}/types-projets", donneesTypeProjet([
            'nom' => 'Musée de la Nouvelle-France',
            'type' => 'musee',
        ]));

    $response->assertRedirectContains('musee-template');
});

test('la création standard redirige vers edit (comportement inchangé)', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = creerCoursMusee($enseignant);

    $response = $this->actingAs($enseignant)
        ->post("/cours/{$cours->id}/types-projets", donneesTypeProjet([
            'nom' => 'Projet recherche',
            'type' => 'standard',
        ]));

    $response->assertRedirectContains('edit');
});

// ─── MuseeTemplateController::edit ────────────────────────────────────────────

test("l'enseignant peut accéder à la page de personnalisation du template", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    [$cours, $typeProjet] = creerTypeProjetMusee($enseignant);

    $this->actingAs($enseignant)
        ->get("/cours/{$cours->id}/types-projets/{$typeProjet->id}/musee-template")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Musee/Template/Edit')
            ->has('template')
            ->where('typeProjet.id', $typeProjet->id)
        );
});

test("un enseignant ne peut pas accéder au template d'un autre enseignant (IDOR)", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $autre = User::factory()->create(['role' => 'enseignant']);
    [$coursAutre, $typeProjetAutre] = creerTypeProjetMusee($autre);

    $this->actingAs($enseignant)
        ->get("/cours/{$coursAutre->id}/types-projets/{$typeProjetAutre->id}/musee-template")
        ->assertForbidden();
});

test('la page template retourne 404 pour un TypeProjet standard', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = creerCoursMusee($enseignant);
    $tp = TypeProjet::create([
        'enseignant_id' => $enseignant->id,
        'cours_id' => $cours->id,
        'nom' => 'Standard',
        'type' => 'standard',
        'accessible' => false,
    ]);

    $this->actingAs($enseignant)
        ->get("/cours/{$cours->id}/types-projets/{$tp->id}/musee-template")
        ->assertNotFound();
});

// ─── MuseeTemplateController::update ──────────────────────────────────────────

test("l'enseignant peut mettre à jour le template visuel", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    [$cours, $typeProjet] = creerTypeProjetMusee($enseignant);

    $this->actingAs($enseignant)
        ->put("/cours/{$cours->id}/types-projets/{$typeProjet->id}/musee-template", [
            'font_titre_page' => 'Playfair Display',
            'font_sous_titre' => 'Lora',
            'font_titre_section' => 'Georgia',
            'font_corps' => 'Open Sans',
            'font_legende' => 'Arial',
            'couleur_fond' => '#FFFFFF',
            'couleur_titre' => '#111111',
            'couleur_corps' => '#333333',
            'couleur_accent' => '#C0392B',
            'couleur_lien_externe' => '#E74C3C',
            'palette' => 'contemporain',
            'theme' => 'clair',
        ])
        ->assertRedirect();

    $template = MuseeTemplate::where('type_projet_id', $typeProjet->id)->first();
    expect($template->font_titre_page)->toBe('Playfair Display');
    expect($template->couleur_fond)->toBe('#FFFFFF');
    expect($template->palette)->toBe('contemporain');
});

test('la mise à jour du template rejette un thème invalide', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    [$cours, $typeProjet] = creerTypeProjetMusee($enseignant);

    $this->actingAs($enseignant)
        ->put("/cours/{$cours->id}/types-projets/{$typeProjet->id}/musee-template", [
            'font_titre_page' => 'Georgia',
            'font_sous_titre' => 'Georgia',
            'font_titre_section' => 'Georgia',
            'font_corps' => 'Arial',
            'font_legende' => 'Arial',
            'couleur_fond' => '#FFFFFF',
            'couleur_titre' => '#111111',
            'couleur_corps' => '#333333',
            'couleur_accent' => '#C0392B',
            'couleur_lien_externe' => '#E74C3C',
            'theme' => 'invalide',
        ])
        ->assertSessionHasErrors('theme');
});

test("un enseignant ne peut pas modifier le template d'un autre (IDOR)", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $autre = User::factory()->create(['role' => 'enseignant']);
    [$coursAutre, $typeProjetAutre] = creerTypeProjetMusee($autre);

    $this->actingAs($enseignant)
        ->put("/cours/{$coursAutre->id}/types-projets/{$typeProjetAutre->id}/musee-template", [
            'font_titre_page' => 'Georgia',
            'font_sous_titre' => 'Georgia',
            'font_titre_section' => 'Georgia',
            'font_corps' => 'Arial',
            'font_legende' => 'Arial',
            'couleur_fond' => '#FFFFFF',
            'couleur_titre' => '#111111',
            'couleur_corps' => '#333333',
            'couleur_accent' => '#C0392B',
            'couleur_lien_externe' => '#E74C3C',
            'theme' => 'clair',
        ])
        ->assertForbidden();
});

test('toCssVariables retourne le bon tableau de variables', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    [, $typeProjet] = creerTypeProjetMusee($enseignant);

    $template = MuseeTemplate::where('type_projet_id', $typeProjet->id)->first();
    $vars = $template->toCssVariables();

    expect($vars)->toHaveKey('--musee-font-titre-page');
    expect($vars)->toHaveKey('--musee-couleur-fond');
    expect($vars['--musee-font-titre-page'])->toBe('Georgia');
    expect($vars['--musee-couleur-fond'])->toBe('#F5EFE0');
});

// ─── MuseeTemplateController::updateCanevas ────────────────────────────────────

test("l'enseignant peut sauvegarder un canevas pour une section", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    [$cours, $typeProjet] = creerTypeProjetMusee($enseignant);

    $section = TypeProjetSection::create([
        'type_projet_id' => $typeProjet->id,
        'label' => 'Section canevas',
        'ordre' => 1,
        'type' => 'texte',
    ]);

    $canevas = [
        'hauteur_vw' => 60,
        'gap' => 4,
        'zones' => [
            [
                'id' => 'zone-aaa',
                'type' => 'texte',
                'label' => 'Zone principale',
                'x' => 0,
                'y' => 0,
                'w' => 60,
                'h' => 100,
                'ordre_mobile' => 1,
            ],
            [
                'id' => 'zone-bbb',
                'type' => 'image',
                'label' => 'Illustration',
                'x' => 60,
                'y' => 0,
                'w' => 40,
                'h' => 100,
                'ordre_mobile' => 2,
            ],
        ],
    ];

    $this->actingAs($enseignant)
        ->patch(
            "/cours/{$cours->id}/types-projets/{$typeProjet->id}/musee-template/sections/{$section->id}/canevas",
            ['canevas' => $canevas],
        )
        ->assertRedirect();

    $section->refresh();
    expect($section->musee_canevas)->not->toBeNull();
    expect($section->musee_canevas['hauteur_vw'])->toBe(60);
    expect($section->musee_canevas['gap'])->toBe(4);
    expect($section->musee_canevas['zones'])->toHaveCount(2);
    expect($section->musee_canevas['zones'][0]['id'])->toBe('zone-aaa');
});

test('updateCanevas accepte le type de zone vide et le persiste', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    [$cours, $typeProjet] = creerTypeProjetMusee($enseignant);

    $section = TypeProjetSection::create([
        'type_projet_id' => $typeProjet->id,
        'label' => 'Section avec espaceur',
        'ordre' => 1,
        'type' => 'musee_contenu',
    ]);

    $this->actingAs($enseignant)
        ->patch(
            "/cours/{$cours->id}/types-projets/{$typeProjet->id}/musee-template/sections/{$section->id}/canevas",
            [
                'canevas' => [
                    'hauteur_vw' => 50,
                    'gap' => 8,
                    'zones' => [
                        ['id' => 'z1', 'type' => 'texte', 'label' => 'Contenu', 'x' => 0, 'y' => 0, 'w' => 70, 'h' => 100, 'ordre_mobile' => 1],
                        ['id' => 'z2', 'type' => 'vide', 'label' => 'Espace', 'x' => 70, 'y' => 0, 'w' => 30, 'h' => 100, 'ordre_mobile' => 2],
                    ],
                ],
            ],
        )
        ->assertRedirect();

    $section->refresh();
    expect($section->musee_canevas['gap'])->toBe(8);
    expect($section->musee_canevas['zones'][1]['type'])->toBe('vide');
});

test('updateCanevas avec null vide le canevas de la section', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    [$cours, $typeProjet] = creerTypeProjetMusee($enseignant);

    $section = TypeProjetSection::create([
        'type_projet_id' => $typeProjet->id,
        'label' => 'Section avec canevas',
        'ordre' => 1,
        'type' => 'texte',
        'musee_canevas' => ['hauteur_vw' => 50, 'zones' => []],
    ]);

    $this->actingAs($enseignant)
        ->patch(
            "/cours/{$cours->id}/types-projets/{$typeProjet->id}/musee-template/sections/{$section->id}/canevas",
            ['canevas' => null],
        )
        ->assertRedirect();

    expect($section->fresh()->musee_canevas)->toBeNull();
});

test('updateCanevas rejette un type de zone invalide', function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    [$cours, $typeProjet] = creerTypeProjetMusee($enseignant);

    $section = TypeProjetSection::create([
        'type_projet_id' => $typeProjet->id,
        'label' => 'Section',
        'ordre' => 1,
        'type' => 'texte',
    ]);

    $this->actingAs($enseignant)
        ->patch(
            "/cours/{$cours->id}/types-projets/{$typeProjet->id}/musee-template/sections/{$section->id}/canevas",
            [
                'canevas' => [
                    'hauteur_vw' => 60,
                    'zones' => [
                        ['id' => 'z1', 'type' => 'invalide', 'label' => 'Zone', 'x' => 0, 'y' => 0, 'w' => 100, 'h' => 100, 'ordre_mobile' => 1],
                    ],
                ],
            ],
        )
        ->assertSessionHasErrors('canevas.zones.0.type');
});

test("un enseignant ne peut pas modifier le canevas d'une section d'un autre cours (IDOR)", function () {
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $autre = User::factory()->create(['role' => 'enseignant']);
    [$coursAutre, $typeProjetAutre] = creerTypeProjetMusee($autre);

    $section = TypeProjetSection::create([
        'type_projet_id' => $typeProjetAutre->id,
        'label' => 'Section autre',
        'ordre' => 1,
        'type' => 'texte',
    ]);

    $this->actingAs($enseignant)
        ->patch(
            "/cours/{$coursAutre->id}/types-projets/{$typeProjetAutre->id}/musee-template/sections/{$section->id}/canevas",
            ['canevas' => null],
        )
        ->assertForbidden();
});
