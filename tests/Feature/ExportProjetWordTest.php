<?php

use App\Actions\ExportProjetWord;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetConclusion;
use App\Models\ProjetRecherche;
use App\Models\ProjetRenvoi;
use App\Models\ProjetSectionContenu;
use App\Models\ProjetSectionParagraphe;
use App\Models\TypeProjet;
use App\Models\TypeProjetSection;
use App\Models\User;
use Illuminate\Testing\TestResponse;

/**
 * Crée un projet de groupe à exporter, avec une section de chaque type rendu par l'export Word.
 *
 * @return array{enseignant: User, cours: Cours, classe: Classe, groupe: Groupe, e1: User, e2: User, typeProjet: TypeProjet, projet: ProjetRecherche}
 */
function creerProjetExportWord(array $optionsTypeProjet = []): array
{
    $enseignant = User::factory()->create(['role' => 'enseignant', 'prenom' => 'Marie', 'nom' => 'Prof']);
    $cours = Cours::create(['nom_cours' => 'Histoire du Québec', 'code' => '330-EW', 'groupe' => '02', 'enseignant_id' => $enseignant->id]);
    $e1 = User::factory()->create(['role' => 'etudiant', 'prenom' => 'Ana', 'nom' => 'Roy']);
    $e2 = User::factory()->create(['role' => 'etudiant', 'prenom' => 'Léo', 'nom' => 'Gay']);

    $classe = Classe::create(['cours_id' => $cours->id]);
    $classe->etudiants()->attach([$e1->id, $e2->id]);
    $groupe = Groupe::create(['classe_id' => $classe->id, 'created_by' => $e1->id]);
    $groupe->membres()->attach([$e1->id, $e2->id]);

    $typeProjet = TypeProjet::create([
        'enseignant_id' => $enseignant->id,
        'cours_id' => $cours->id,
        'nom' => 'TP',
        'accessible' => true,
        ...$optionsTypeProjet,
    ]);
    $projet = ProjetRecherche::create(['groupe_id' => $groupe->id, 'type_projet_id' => $typeProjet->id, 'titre_projet' => 'La Nouvelle-France']);

    return compact('enseignant', 'cours', 'classe', 'groupe', 'e1', 'e2', 'typeProjet', 'projet');
}

/**
 * Télécharge l'export Word et retourne le XML de word/document.xml.
 */
function documentXmlExportWord(TestResponse $response): string
{
    $fichier = tempnam(sys_get_temp_dir(), 'test_docx_');
    file_put_contents($fichier, $response->streamedContent());

    $zip = new ZipArchive;
    $zip->open($fichier);
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    unlink($fichier);

    return $xml;
}

/**
 * Retourne le texte visible du document, un fragment par élément w:t.
 */
function texteExportWord(string $xml): string
{
    preg_match_all('/<w:t(?:\s[^>]*)?>([^<]*)<\/w:t>/', $xml, $m);

    return html_entity_decode(implode('|', $m[1]), ENT_QUOTES | ENT_XML1);
}

/**
 * URL de l'export Word du projet.
 */
function urlExportWord(Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet): string
{
    return "/cours/{$cours->id}/classes/{$classe->id}/groupes/{$groupe->id}/projets/{$typeProjet->id}/word";
}

test("l'export Word contient page titre, table des matières, sections et références dans l'ordre", function () {
    $this->travelTo(now()->setDate(2026, 3, 15));
    ['enseignant' => $enseignant, 'cours' => $cours, 'classe' => $classe, 'groupe' => $groupe, 'e1' => $e1, 'e2' => $e2, 'typeProjet' => $tp, 'projet' => $projet]
        = creerProjetExportWord(['generer_page_titre' => true, 'generer_table_matieres' => true]);

    $intro = TypeProjetSection::create(['type_projet_id' => $tp->id, 'label' => 'Introduction', 'ordre' => 1, 'type' => 'texte']);
    $dev = TypeProjetSection::create(['type_projet_id' => $tp->id, 'label' => 'Développement', 'ordre' => 2, 'type' => 'paragraphes']);
    $concl = TypeProjetSection::create(['type_projet_id' => $tp->id, 'label' => 'Conclusion', 'ordre' => 3, 'type' => 'individuel']);
    TypeProjetSection::create(['type_projet_id' => $tp->id, 'label' => 'Vide', 'ordre' => 4, 'type' => 'texte']);

    ProjetSectionContenu::create(['projet_id' => $projet->id, 'section_id' => $intro->id, 'contenu' => '<p>Texte <mark data-comment-id="3">annoté</mark> intro</p>']);
    // Insérés dans le désordre : l'export trie par ordre
    ProjetSectionParagraphe::create(['projet_id' => $projet->id, 'section_id' => $dev->id, 'ordre' => 2, 'titre' => '', 'contenu' => '<p>Second</p>']);
    ProjetSectionParagraphe::create(['projet_id' => $projet->id, 'section_id' => $dev->id, 'ordre' => 1, 'titre' => 'Premier titre', 'contenu' => '<p>Premier</p>']);
    ProjetConclusion::create(['projet_id' => $projet->id, 'user_id' => $e1->id, 'section_id' => $concl->id, 'contenu' => '<p>Conclusion Ana</p>']);
    ProjetRenvoi::create(['projet_id' => $projet->id, 'numero' => 1, 'contenu' => 'Source & cie']);

    $response = $this->actingAs($e1)->get(urlExportWord($cours, $classe, $groupe, $tp));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
        ->assertDownload("projet_groupe_{$groupe->numero}.docx");

    $xml = documentXmlExportWord($response);

    expect(texteExportWord($xml))->toContain(implode('|', [
        'Ana Roy', 'Léo Gay', 'Histoire du Québec', '330-EW / Gr. 02', 'LA NOUVELLE-FRANCE', 'RECHERCHE DOCUMENTAIRE',
        'Travail présenté à', 'Marie Prof', 'Département des sciences humaines', 'Cégep de Drummondville', 'Le '.now()->translatedFormat('j F Y'),
        'TABLE DES MATIÈRES',
    ]))
        ->and(texteExportWord($xml))->toContain('Introduction|Texte annoté intro')
        ->and(texteExportWord($xml))->toContain('Développement|Premier titre|Premier|Paragraphe 2|Second')
        ->and(texteExportWord($xml))->toContain('Conclusion|Ana Roy|Conclusion Ana|Léo Gay|(Section non rédigée)')
        ->and(texteExportWord($xml))->toContain("Vide|(Section non rédigée)|Références|1.\tSource & cie")
        // Correctifs appliqués au champ TOC de PhpWord
        ->and($xml)->toContain('TOC \o "1-2"')
        ->and($xml)->not->toMatch('/PAGEREF \d+ \\\\h/');
});

test("l'export Word utilise le contenu manuel quand la génération est désactivée", function () {
    ['cours' => $cours, 'classe' => $classe, 'groupe' => $groupe, 'e1' => $e1, 'typeProjet' => $tp, 'projet' => $projet]
        = creerProjetExportWord(['generer_page_titre' => false, 'generer_table_matieres' => false]);
    $projet->update(['page_titre_contenu' => '<p>Ma page titre</p>', 'table_matieres_contenu' => '<p>Ma TDM</p>']);

    $xml = documentXmlExportWord($this->actingAs($e1)->get(urlExportWord($cours, $classe, $groupe, $tp)));

    // Sans section définie, le contenu tombe sur le repli « non rédigée » ; sans renvoi, pas de références
    expect(texteExportWord($xml))->toBe('Ma page titre|Ma TDM|(Section non rédigée)')
        ->and($xml)->not->toContain('TOC \o');
});

test("l'export Word ne met pas de titre par membre quand le groupe n'a qu'un membre", function () {
    ['cours' => $cours, 'classe' => $classe, 'groupe' => $groupe, 'e1' => $e1, 'e2' => $e2, 'typeProjet' => $tp, 'projet' => $projet]
        = creerProjetExportWord(['generer_page_titre' => false, 'generer_table_matieres' => false]);
    $groupe->membres()->detach($e2->id);
    $concl = TypeProjetSection::create(['type_projet_id' => $tp->id, 'label' => 'Conclusion', 'ordre' => 1, 'type' => 'individuel']);
    ProjetConclusion::create(['projet_id' => $projet->id, 'user_id' => $e1->id, 'section_id' => $concl->id, 'contenu' => '<p>Seule</p>']);

    $xml = documentXmlExportWord($this->actingAs($e1)->get(urlExportWord($cours, $classe, $groupe, $tp)));

    expect(texteExportWord($xml))->toBe('Conclusion|Seule');
});

test("le fichier Word est nommé d'après le numéro du groupe dans sa classe, comme le PDF", function () {
    ['cours' => $cours, 'e1' => $e1, 'typeProjet' => $tp] = creerProjetExportWord();

    // Groupe d'une seconde classe : numero = 1 alors que son id vaut au moins 2
    $autreClasse = Classe::create(['cours_id' => $cours->id]);
    $groupe = Groupe::create(['classe_id' => $autreClasse->id, 'created_by' => $e1->id]);
    $groupe->membres()->attach($e1->id);
    $projet = ProjetRecherche::create(['groupe_id' => $groupe->id, 'type_projet_id' => $tp->id]);

    $response = TestResponse::fromBaseResponse((new ExportProjetWord)->execute($projet, $groupe));

    expect($groupe->numero)->toBe(1)->and($groupe->id)->toBeGreaterThan(1);
    $response->assertDownload('projet_groupe_1.docx');
});

test("sans type de projet, l'export Word génère la page titre et la table des matières, comme le PDF", function () {
    ['groupe' => $groupe] = creerProjetExportWord();
    $projet = ProjetRecherche::create(['groupe_id' => $groupe->id, 'type_projet_id' => null, 'titre_projet' => 'Sans type']);

    $xml = documentXmlExportWord(TestResponse::fromBaseResponse((new ExportProjetWord)->execute($projet, $groupe)));

    expect(texteExportWord($xml))->toContain('SANS TYPE|RECHERCHE DOCUMENTAIRE')
        // Le champ TOC lui-même n'est écrit par PhpWord que s'il existe des titres de section
        ->and(texteExportWord($xml))->toContain('TABLE DES MATIÈRES');
});

test("l'export Word ne laisse aucun fichier temporaire derrière lui", function () {
    ['cours' => $cours, 'classe' => $classe, 'groupe' => $groupe, 'e1' => $e1, 'typeProjet' => $tp] = creerProjetExportWord();
    // Sous Windows, tempnam() ne garde que les 3 premiers caractères du préfixe (php*.tmp)
    $avant = glob(sys_get_temp_dir().DIRECTORY_SEPARATOR.'php*') ?: [];

    $this->actingAs($e1)->get(urlExportWord($cours, $classe, $groupe, $tp))->streamedContent();

    expect(array_diff(glob(sys_get_temp_dir().DIRECTORY_SEPARATOR.'php*') ?: [], $avant))->toBeEmpty();
});
