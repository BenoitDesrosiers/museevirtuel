<?php

use App\Actions\ExportProjetPdf;
use App\Helpers\HtmlHelper;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetConclusion;
use App\Models\ProjetRecherche;
use App\Models\ProjetSectionContenu;
use App\Models\ProjetSectionParagraphe;
use App\Models\TypeProjet;
use App\Models\TypeProjetSection;
use App\Models\User;
use Illuminate\Support\Facades\View;

/**
 * Crée un projet à deux membres avec une section de chaque type rendu dans la TDM :
 * texte, paragraphes (2 paragraphes) et individuel (une conclusion par membre).
 *
 * @param  array<string, mixed>  $flags
 * @return array{projet: ProjetRecherche, groupe: Groupe, membres: list<User>}
 */
function creerProjetPdfAvecSections(array $flags = []): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant']);
    $cours = Cours::create([
        'nom_cours' => 'Histoire', 'code' => '330-PDF', 'groupe' => '01',
        'enseignant_id' => $enseignant->id,
    ]);
    $typeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id, 'cours_id' => $cours->id, 'nom' => 'Projet PDF',
        'generer_page_titre' => true, 'generer_table_matieres' => true, ...$flags,
    ]);

    $membres = User::factory()->count(2)->create(['role' => 'etudiant'])->all();
    $classe = Classe::create(['cours_id' => $cours->id]);
    $groupe = Groupe::create(['classe_id' => $classe->id, 'created_by' => $membres[0]->id]);
    $groupe->membres()->attach(array_map(fn (User $m) => $m->id, $membres));

    $projet = ProjetRecherche::create(['groupe_id' => $groupe->id, 'type_projet_id' => $typeProjet->id]);

    $section = fn (int $ordre, string $type) => TypeProjetSection::create([
        'type_projet_id' => $typeProjet->id, 'label' => "Section {$type}", 'ordre' => $ordre, 'type' => $type,
    ]);

    $texte = $section(1, 'texte');
    $paragraphes = $section(2, 'paragraphes');
    $individuel = $section(3, 'individuel');

    ProjetSectionContenu::create(['projet_id' => $projet->id, 'section_id' => $texte->id, 'contenu' => '<p>Contenu texte</p>']);
    // Insérés dans le désordre pour vérifier le tri par ordre
    ProjetSectionParagraphe::create(['projet_id' => $projet->id, 'section_id' => $paragraphes->id, 'ordre' => 2, 'titre' => 'Deuxième', 'contenu' => '<p>B</p>']);
    ProjetSectionParagraphe::create(['projet_id' => $projet->id, 'section_id' => $paragraphes->id, 'ordre' => 1, 'titre' => 'Premier', 'contenu' => '<p>A</p>']);

    foreach ($membres as $membre) {
        ProjetConclusion::create([
            'projet_id' => $projet->id, 'user_id' => $membre->id,
            'section_id' => $individuel->id, 'contenu' => "<p>Conclusion {$membre->id}</p>",
        ]);
    }

    return ['projet' => $projet, 'groupe' => $groupe->fresh(), 'membres' => $membres];
}

/**
 * Exécute l'export PDF et retourne les données passées à la vue à chaque passe de rendu.
 *
 * @return list<array<string, mixed>>
 */
function capturerPassesPdf(ProjetRecherche $projet, Groupe $groupe): array
{
    $passes = [];
    View::composer('projets.export', function ($view) use (&$passes) {
        $passes[] = $view->getData();
    });

    (new ExportProjetPdf)->execute($projet, $groupe);

    return $passes;
}

test("l'export PDF retourne un fichier nommé d'après le numéro du groupe", function () {
    ['projet' => $projet, 'groupe' => $groupe] = creerProjetPdfAvecSections();

    $response = (new ExportProjetPdf)->execute($projet, $groupe);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($response->headers->get('Content-Disposition'))
        ->toBe('attachment; filename="projet_groupe_'.$groupe->numero.'.pdf"')
        ->and($response->getContent())->toStartWith('%PDF');
});

test('la TDM fait deux passes et numérote chaque section et sous-section à partir de la première page de contenu', function () {
    ['projet' => $projet, 'groupe' => $groupe] = creerProjetPdfAvecSections();

    $passes = capturerPassesPdf($projet, $groupe);

    expect($passes)->toHaveCount(2)
        ->and($passes[0]['tocPageNums'])->toBe([]);

    $numeros = $passes[1]['tocPageNums'];

    expect(array_keys($numeros))->toBe([
        'section-0', 'section-1', 'subsection-1-0', 'subsection-1-1',
        'section-2', 'member-2-0', 'member-2-1',
    ])
        // Page titre et TDM sont exclues : le contenu commence à la page visible 1
        ->and($numeros['section-0'])->toBe(1)
        ->and($numeros)->each->toBeInt()->toBeGreaterThanOrEqual(1);

    $valeurs = array_values($numeros);
    $triees = $valeurs;
    sort($triees);
    expect($valeurs)->toBe($triees);
});

test('sans TDM générée, le PDF est rendu en une seule passe sans numéros de TDM', function () {
    ['projet' => $projet, 'groupe' => $groupe] = creerProjetPdfAvecSections(['generer_table_matieres' => false]);

    $passes = capturerPassesPdf($projet, $groupe);

    expect($passes)->toHaveCount(1)
        ->and($passes[0]['tocPageNums'])->toBe([])
        ->and($passes[0]['genererTableMatieres'])->toBeFalse();
});

test('les sections passées à la vue sont nettoyées, triées et indexées par membre', function () {
    ['projet' => $projet, 'groupe' => $groupe, 'membres' => $membres] = creerProjetPdfAvecSections();

    $donnees = capturerPassesPdf($projet, $groupe)[1];
    $nettoyer = fn (string $html) => HtmlHelper::addRenvoisLinks(HtmlHelper::stripAnnotationMarks($html));
    [$texte, $paragraphes, $individuel] = $donnees['sections']->all();

    expect($donnees['membres']->all())->toBe(array_map(fn (User $m) => "{$m->prenom} {$m->nom}", $membres))
        ->and($donnees['genererPageTitre'])->toBeTrue()
        ->and(($donnees['stripMarks'])('<p>x</p>'))->toBe($nettoyer('<p>x</p>'))
        ->and($texte)->toMatchArray(['label' => 'Section texte', 'type' => 'texte', 'contenu' => $nettoyer('<p>Contenu texte</p>')])
        ->and($paragraphes['paragraphes']->all())->toBe([
            ['titre' => 'Premier', 'contenu' => $nettoyer('<p>A</p>')],
            ['titre' => 'Deuxième', 'contenu' => $nettoyer('<p>B</p>')],
        ])
        ->and($individuel['membres_conclusions']->all())->toBe([
            $membres[0]->id => $nettoyer("<p>Conclusion {$membres[0]->id}</p>"),
            $membres[1]->id => $nettoyer("<p>Conclusion {$membres[1]->id}</p>"),
        ]);
});

test("le contenu exporté perd ses marques d'annotation et gagne un lien vers sa référence", function () {
    ['projet' => $projet, 'groupe' => $groupe] = creerProjetPdfAvecSections();
    $projet->sectionContenus()->update([
        'contenu' => '<p>Texte <mark data-comment-id="4">annoté</mark><sup data-renvoi-id="1" data-renvoi-numero="1">1</sup></p>',
    ]);

    $contenu = capturerPassesPdf($projet, $groupe)[1]['sections'][0]['contenu'];

    expect($contenu)->not->toContain('<mark')
        ->and($contenu)->toContain('annoté')
        ->and($contenu)->toContain('href="#ref-1"')
        ->and($contenu)->toContain('id="appel-1-1"');
});

test('sans page titre, la TDM numérote quand même le contenu à partir de 1', function () {
    // Seule la TDM est exclue de la numérotation : le décalage passe de 2 pages à 1
    ['projet' => $projet, 'groupe' => $groupe] = creerProjetPdfAvecSections(['generer_page_titre' => false]);

    $numeros = capturerPassesPdf($projet, $groupe)[1]['tocPageNums'];

    expect($numeros['section-0'])->toBe(1);
});
