<?php

namespace App\Actions;

use App\Helpers\HtmlHelper;
use App\Models\Groupe;
use App\Models\ProjetConclusion;
use App\Models\ProjetRecherche;
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
     * Structure :
     *  - Page titre
     *  - Table des matières (champ TOC Word, mise à jour à l'ouverture)
     *  - Introduction (Heading 1) — amener/poser/diviser en texte continu
     *  - Développement (Heading 1) — chaque sous-section en Heading 2
     *  - Conclusion (Heading 1) — un Heading 2 par membre si > 1 membre
     */
    public function execute(ProjetRecherche $projet, Groupe $groupe): StreamedResponse
    {
        $classe = $groupe->classe;
        $enseignant = $classe->enseignant;

        $word = new PhpWord;
        $word->setDefaultFontName('Times New Roman');
        $word->setDefaultFontSize(12);

        // ─── Page titre ───────────────────────────────────────────────────────
        $pageTitre = $word->addSection();

        foreach ($groupe->membres as $membre) {
            $this->addCenteredText($pageTitre, "{$membre->prenom} {$membre->nom}");
        }

        $this->addCenteredText($pageTitre, $classe->nom_cours);
        $this->addCenteredText($pageTitre, "{$classe->code} / Gr. {$classe->groupe}", 10);
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

        // ─── Sections dynamiques ou Introduction classique ───────────────────
        if (! $projet->relationLoaded('typeProjet')) {
            $projet->load(['typeProjet.sections', 'sectionContenus']);
        }
        $sections = $projet->typeProjet?->sections ?? collect();

        if ($sections->isNotEmpty()) {
            $contenusParSection = $projet->sectionContenus->keyBy('section_id');

            foreach ($sections as $index => $section) {
                $sectionWord = $word->addSection();
                $sectionWord->addTitle($section->label, 1);
                $sectionWord->addTextBreak(1);
                $contenu = HtmlHelper::stripAnnotationMarks($contenusParSection->get($section->id)?->contenu);
                $this->addHtmlContent($sectionWord, $contenu);
            }
        } else {
            // Fallback : structure classique Introduction
            $introSection = $word->addSection();
            $introSection->addTitle('Introduction', 1);
            $introSection->addTextBreak(1);
            $this->addHtmlContent($introSection, $projet->introduction_amener);
            $this->addHtmlContent($introSection, $projet->introduction_poser);
            $this->addHtmlContent($introSection, $projet->introduction_diviser);
        }

        // ─── Développement (Heading 1) + sous-sections (Heading 2) ───────────
        $developpements = $projet->relationLoaded('developpements')
            ? $projet->developpements
            : $projet->developpements()->get();

        $devMainSection = $word->addSection();
        $devMainSection->addTitle('Développement', 1);
        $devMainSection->addTextBreak(1);

        foreach ($developpements as $index => $dev) {
            // Le premier paragraphe partage la section du H1 "Développement"
            $devSection = ($index === 0) ? $devMainSection : $word->addSection();
            $titreDev = $dev->titre ?: "Paragraphe de développement {$dev->ordre}";
            $devSection->addTitle($titreDev, 2);
            $devSection->addTextBreak(1);
            $this->addHtmlContent($devSection, $dev->contenu);
        }

        // Fallback si aucun paragraphe n'existe (projet vide)
        if ($developpements->isEmpty()) {
            $this->addHtmlContent($devMainSection, null);
        }

        // ─── Conclusions (Heading 1 global, Heading 2 par membre si > 1) ─────
        $nbMembres = $groupe->membres->count();
        $isFirst = true;

        foreach ($groupe->membres as $membre) {
            /** @var ProjetConclusion|null $conclusion */
            $conclusion = $projet->conclusions->firstWhere('user_id', $membre->id);

            $conclusionSection = $word->addSection();

            // "Conclusion" en Heading 1 uniquement pour le premier membre
            if ($isFirst) {
                $conclusionSection->addTitle('Conclusion', 1);
                $conclusionSection->addTextBreak(1);
                $isFirst = false;
            }

            // Heading 2 par membre uniquement si le groupe compte plusieurs membres
            if ($nbMembres > 1) {
                $conclusionSection->addTitle("{$membre->prenom} {$membre->nom}", 2);
                $conclusionSection->addTextBreak(1);
            }

            $this->addHtmlContent($conclusionSection, $conclusion?->contenu);
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
