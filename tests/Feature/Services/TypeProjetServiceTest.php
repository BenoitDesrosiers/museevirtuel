<?php

use App\Enums\TypeSection;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetRecherche;
use App\Models\User;
use App\Services\TypeProjetService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Enseignant et son cours.
 */
function scenarioTypeProjetService(): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = Cours::create([
        'nom_cours' => 'Histoire',
        'code' => '330-TPS',
        'groupe' => 'A',
        'enseignant_id' => $enseignant->id,
    ]);

    return compact('enseignant', 'cours');
}

test('creer applique les valeurs par défaut et crée les sections dans l\'ordre', function () {
    ['enseignant' => $enseignant, 'cours' => $cours] = scenarioTypeProjetService();

    $typeProjet = app(TypeProjetService::class)->creer($enseignant, $cours, donneesTypeProjet([
        'sections' => [
            ['label' => 'Introduction'],
            ['label' => 'Analyse', 'type' => TypeSection::Texte->value, 'description' => 'Détails'],
        ],
    ]));

    expect($typeProjet->accessible)->toBeFalse()
        ->and($typeProjet->generer_page_titre)->toBeTrue()
        ->and($typeProjet->generer_table_matieres)->toBeTrue()
        ->and($typeProjet->is_sommatif)->toBeTrue()
        ->and($typeProjet->remises_multiples)->toBeFalse()
        ->and($typeProjet->sections()->orderBy('ordre')->pluck('label')->all())->toBe(['Introduction', 'Analyse']);
});

test('creer initialise le critère de publication et la section d\'un projet musée', function () {
    ['enseignant' => $enseignant, 'cours' => $cours] = scenarioTypeProjetService();

    $typeProjet = app(TypeProjetService::class)->creer($enseignant, $cours, donneesTypeProjet(['type' => 'musee']));

    expect($typeProjet->criteres()->where('is_musee_publication', true)->value('pointage'))->toEqual(20)
        ->and($typeProjet->sections()->pluck('type')->map(fn ($t) => $t instanceof TypeSection ? $t->value : $t)->all())
        ->toBe([TypeSection::MuseeContenu->value]);
});

test('mettreAJour synchronise les sections : supprime, met à jour et crée', function () {
    ['enseignant' => $enseignant, 'cours' => $cours] = scenarioTypeProjetService();
    $service = app(TypeProjetService::class);
    $typeProjet = $service->creer($enseignant, $cours, donneesTypeProjet([
        'sections' => [['label' => 'Gardée'], ['label' => 'Supprimée']],
    ]));
    $gardee = $typeProjet->sections()->where('label', 'Gardée')->first();

    $service->mettreAJour($typeProjet, donneesTypeProjet([
        'sections' => [
            ['label' => 'Nouvelle'],
            ['id' => $gardee->id, 'label' => 'Gardée renommée'],
        ],
    ]));

    expect($typeProjet->sections()->orderBy('ordre')->pluck('label')->all())->toBe(['Nouvelle', 'Gardée renommée'])
        ->and($gardee->fresh()->ordre)->toBe(2);
});

test('mettreAJour ne touche pas aux sections quand elles sont absentes des données', function () {
    ['enseignant' => $enseignant, 'cours' => $cours] = scenarioTypeProjetService();
    $service = app(TypeProjetService::class);
    $typeProjet = $service->creer($enseignant, $cours, donneesTypeProjet(['sections' => [['label' => 'Intacte']]]));

    $service->mettreAJour($typeProjet, donneesTypeProjet(['nom' => 'Renommé']));

    expect($typeProjet->fresh()->nom)->toBe('Renommé')
        ->and($typeProjet->sections()->pluck('label')->all())->toBe(['Intacte']);
});

test('mettreAJour garde les booléens absents et vide la page titre en passant en manuel', function () {
    ['enseignant' => $enseignant, 'cours' => $cours] = scenarioTypeProjetService();
    $service = app(TypeProjetService::class);
    $typeProjet = $service->creer($enseignant, $cours, donneesTypeProjet(['remises_multiples' => true]));

    $etudiant = User::factory()->create(['role' => 'etudiant']);
    $classe = Classe::create(['cours_id' => $cours->id]);
    $groupe = Groupe::create(['classe_id' => $classe->id, 'created_by' => $etudiant->id]);
    $projet = ProjetRecherche::create([
        'groupe_id' => $groupe->id,
        'type_projet_id' => $typeProjet->id,
        'page_titre_contenu' => '<p>Titre généré</p>',
    ]);

    $service->mettreAJour($typeProjet, donneesTypeProjet(['generer_page_titre' => false]));

    expect($typeProjet->fresh()->remises_multiples)->toBeTrue()
        ->and($typeProjet->fresh()->generer_page_titre)->toBeFalse()
        ->and($projet->fresh()->page_titre_contenu)->toBeNull();
});
