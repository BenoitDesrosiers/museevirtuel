<?php

namespace App\Actions;

use App\Helpers\HtmlHelper;
use App\Models\Groupe;
use App\Models\ProjetRecherche;
use App\Models\TypeProjetSection;
use App\Models\User;
use Illuminate\Support\Collection;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Html;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportProjetWord
{
    /**
     * Génère et retourne le projet de groupe en .docx.
     *
     * Structure générée à partir des sections typées du TypeProjet :
     *  - Page titre
     *  - Table des matières (champ TOC Word, mise à jour à l'ouverture)
     *  - Une section Word par TypeProjetSection :
     *      • texte       → Heading 1 + contenu HTML
     *      • paragraphes → Heading 1 + sous-sections Heading 2 (titre + contenu)
     *      • individuel  → Heading 1 + un Heading 2 par membre (si > 1 membre)
     */
    public function execute(ProjetRecherche $projet, Groupe $groupe): StreamedResponse
    {
        $cours = $groupe->classe->cours;
        $enseignant = $cours->enseignant;

        if (! $projet->relationLoaded('typeProjet')) {
            $projet->load(['typeProjet.sections', 'sectionContenus', 'sectionParagraphes', 'conclusions']);
        }

        $word = new PhpWord;
        $word->setDefaultFontName('Times New Roman');
        $word->setDefaultFontSize(12);

        // ─── Page titre ───────────────────────────────────────────────────────
        $pageTitre = $word->addSection();

        foreach ($groupe->membres as $membre) {
            $this->addCenteredText($pageTitre, "{$membre->prenom} {$membre->nom}");
        }

        $this->addCenteredText($pageTitre, $cours->nom_cours);
        $this->addCenteredText($pageTitre, "{$cours->code} / Gr. {$cours->groupe}", 10);
        $pageTitre->addTextBreak(3);
        $this->addCenteredText($pageTitre, strtoupper($projet->titre_projet ?? 'Recherche documentaire'), 16, true);
        $this->addCenteredText($pageTitre, 'RECHERCHE DOCUMENTAIRE');
        $pageTitre->addTextBreak(3);
        $this->addCenteredText($pageTitre, 'Travail présenté à');
        $this->addCenteredText($pageTitre, "{$enseignant->prenom} {$enseignant->nom}", 12, true);
        $pageTitre->addTextBreak(2);
        $this->addCenteredText($pageTitre, 'Département des sciences humaines', 10);
        $this->addCenteredText($pageTitre, 'Cégep de Drummondville', 10);
        $this->addCenteredText($pageTitre, 'Le '.now()->translatedFormat('j F Y'), 10);

        // ─── Table des matières (champ TOC Word — Heading 1 & 2) ─────────────
        $tocSection = $word->addSection();
        $tocSection->addText(
            'TABLE DES MATIÈRES',
            ['bold' => true, 'size' => 13, 'allCaps' => true],
            ['alignment' => 'center'],
        );
        $tocSection->addTextBreak(1);
        // Champ TOC automatique : se met à jour à l'ouverture du fichier dans Word
        $tocSection->addTOC(['size' => 11], null, 1, 2);

        // ─── Sections dynamiques ──────────────────────────────────────────────
        $sections = $projet->typeProjet?->sections ?? collect();
        $contenusParSection = $projet->sectionContenus->keyBy('section_id');
        $paragraphesParSection = $projet->sectionParagraphes->groupBy('section_id');
        $conclusionsParUser = $projet->conclusions
            ->filter(fn ($c) => $c->section_id !== null)
            ->groupBy('section_id')
            ->map(fn ($conc) => $conc->keyBy('user_id'));

        $nbMembres = $groupe->membres->count();

        foreach ($sections as $typeSection) {
            /** @var TypeProjetSection $typeSection */
            $sectionWord = $word->addSection();
            $sectionWord->addTitle($typeSection->label, 1);
            $sectionWord->addTextBreak(1);

            match ($typeSection->type ?? 'texte') {
                'paragraphes' => $this->renderParagraphes(
                    $word,
                    $sectionWord,
                    $paragraphesParSection->get($typeSection->id) ?? collect()
                ),
                'individuel' => $this->renderIndividuel(
                    $word,
                    $sectionWord,
                    $groupe->membres,
                    $conclusionsParUser->get($typeSection->id) ?? collect(),
                    $nbMembres
                ),
                default => $this->addHtmlContent(
                    $sectionWord,
                    $contenusParSection->get($typeSection->id)?->contenu
                ),
            };
        }

        // Fallback si aucune section définie (projet sans TypeProjet configuré)
        if ($sections->isEmpty()) {
            $vide = $word->addSection();
            $this->addHtmlContent($vide, null);
        }

        // ─── Stream du fichier ────────────────────────────────────────────────
        $nomFichier = sprintf('projet_groupe_%d.docx', $groupe->id);

        return response()->streamDownload(function () use ($word) {
            $writer = IOFactory::createWriter($word, 'Word2007');
            $writer->save('php://output');
        }, $nomFichier, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    /**
     * Ajoute les paragraphes d'une section de type 'paragraphes' comme sous-sections (Heading 2).
     *
     * @param  Collection  $paragraphes
     */
    private function renderParagraphes(PhpWord $word, Section $firstSection, $paragraphes): void
    {
        if ($paragraphes->isEmpty()) {
            $this->addHtmlContent($firstSection, null);

            return;
        }

        foreach ($paragraphes->sortBy('ordre') as $index => $paragraphe) {
            // Le premier partage la section du H1
            $sectionParagraphe = ($index === 0) ? $firstSection : $word->addSection();
            $titre = $paragraphe->titre ?: "Paragraphe {$paragraphe->ordre}";
            $sectionParagraphe->addTitle($titre, 2);
            $sectionParagraphe->addTextBreak(1);
            $this->addHtmlContent($sectionParagraphe, $paragraphe->contenu);
        }
    }

    /**
     * Ajoute les conclusions individuelles d'une section de type 'individuel'.
     *
     * @param  Collection  $membres
     * @param  Collection  $conclusionsParUser  keyBy user_id
     */
    private function renderIndividuel(PhpWord $word, Section $firstSection, $membres, $conclusionsParUser, int $nbMembres): void
    {
        $isFirst = true;

        foreach ($membres as $membre) {
            /** @var User $membre */
            $sectionConclusion = $isFirst ? $firstSection : $word->addSection();

            if ($nbMembres > 1) {
                $sectionConclusion->addTitle("{$membre->prenom} {$membre->nom}", 2);
                $sectionConclusion->addTextBreak(1);
            }

            $conclusion = $conclusionsParUser->get($membre->id);
            $this->addHtmlContent($sectionConclusion, $conclusion?->contenu);

            $isFirst = false;
        }
    }

    /**
     * Ajoute un paragraphe centré dans une section Word.
     */
    private function addCenteredText(Section $section, string $text, int $size = 12, bool $bold = false): void
    {
        $section->addText(
            htmlspecialchars($text),
            ['size' => $size, 'bold' => $bold],
            ['alignment' => 'center'],
        );
    }

    /**
     * Ajoute du contenu HTML (issu de TipTap) dans une section Word.
     * Utilise le parser HTML de PhpWord avec fallback sur texte brut.
     */
    private function addHtmlContent(Section $section, ?string $html): void
    {
        // Retirer les marques d'annotation avant export — l'enseignant voit le texte brut
        $html = HtmlHelper::stripAnnotationMarks($html);

        if (empty($html) || trim(strip_tags($html)) === '') {
            $section->addText('(Section non rédigée)', ['italic' => true, 'color' => '999999']);
            $section->addTextBreak(1);

            return;
        }

        try {
            Html::addHtml($section, $html, false, false);
        } catch (\Throwable) {
            // Fallback : texte brut si le parser HTML échoue
            $section->addText(htmlspecialchars(strip_tags($html)));
        }

        $section->addTextBreak(1);
    }
}
