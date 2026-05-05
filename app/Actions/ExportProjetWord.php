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
use PhpOffice\PhpWord\SimpleType\Jc;
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
            $projet->load(['typeProjet.sections', 'sectionContenus', 'sectionParagraphes', 'conclusions', 'renvois']);
        } elseif (! $projet->relationLoaded('renvois')) {
            $projet->load(['renvois']);
        }

        $typeProjet = $projet->typeProjet;

        $word = new PhpWord;
        $word->setDefaultFontName('Times New Roman');
        $word->setDefaultFontSize(12);
        // Force Word à recalculer la TDM (numéros de page) dès l'ouverture du fichier
        $word->getSettings()->setUpdateFields(true);

        // Enregistrer les styles Heading dans la feuille de styles du document —
        // sans cela, addTitle() crée les paragraphes mais Word ne les reconnaît pas
        // comme entrées de TOC lors de la mise à jour du champ.
        $word->addTitleStyle(1, ['bold' => true, 'size' => 14], ['spaceAfter' => 200, 'spaceBefore' => 240]);
        $word->addTitleStyle(2, ['bold' => true, 'size' => 12], ['spaceAfter' => 160, 'spaceBefore' => 160]);

        // ─── Page titre ───────────────────────────────────────────────────────
        if ($typeProjet && $typeProjet->generer_page_titre) {
            $pageTitre = $this->newSection($word, false);

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
            $this->addCenteredText($pageTitre, $enseignant->etablissement?->nom ?? 'Cégep de Drummondville', 10);
            $this->addCenteredText($pageTitre, 'Le '.now()->translatedFormat('j F Y'), 10);
        } elseif (! empty($projet->page_titre_contenu)) {
            // Contenu rédigé manuellement par l'étudiant
            $pageTitre = $this->newSection($word, false);
            $this->addHtmlContent($pageTitre, $projet->page_titre_contenu);
        }

        // ─── Table des matières (champ TOC Word — Heading 1 & 2) ─────────────
        if ($typeProjet && $typeProjet->generer_table_matieres) {
            $tocSection = $this->newSection($word, false);
            $tocSection->addText(
                'TABLE DES MATIÈRES',
                ['bold' => true, 'size' => 13, 'allCaps' => true],
                ['alignment' => 'center'],
            );
            $tocSection->addTextBreak(1);
            // Champ TOC automatique : se met à jour à l'ouverture du fichier dans Word
            $tocSection->addTOC(['size' => 11], null, 1, 2);
        } elseif (! empty($projet->table_matieres_contenu)) {
            // Contenu rédigé manuellement par l'étudiant
            $tocSection = $this->newSection($word, false);
            $this->addHtmlContent($tocSection, $projet->table_matieres_contenu);
        }

        // ─── Sections dynamiques ──────────────────────────────────────────────
        $sections = $projet->typeProjet?->sections ?? collect();
        $contenusParSection = $projet->sectionContenus->keyBy('section_id');
        $paragraphesParSection = $projet->sectionParagraphes->groupBy('section_id');
        $conclusionsParUser = $projet->conclusions
            ->filter(fn ($c) => $c->section_id !== null)
            ->groupBy('section_id')
            ->map(fn ($conc) => $conc->keyBy('user_id'));

        $nbMembres = $groupe->membres->count();

        // Tout le contenu dans UNE SEULE section Word (pageNumberingStart = 1) afin que
        // la numérotation soit continue et que la TOC reflète les vrais numéros de page.
        // Les séparations entre TypeProjetSections sont des sauts de page simples (pas
        // des sauts de section), ce qui évite tout redémarrage implicite du compteur.
        $contenu = $this->newSection($word, true, 1);
        $aContenu = false;

        foreach ($sections as $typeSection) {
            /** @var TypeProjetSection $typeSection */
            if ($aContenu) {
                $contenu->addPageBreak();
            }
            $aContenu = true;

            $contenu->addTitle($typeSection->label, 1);
            $contenu->addTextBreak(1);

            match ($typeSection->type ?? 'texte') {
                'paragraphes' => $this->renderParagraphes(
                    $contenu,
                    $paragraphesParSection->get($typeSection->id) ?? collect()
                ),
                'individuel' => $this->renderIndividuel(
                    $contenu,
                    $groupe->membres,
                    $conclusionsParUser->get($typeSection->id) ?? collect(),
                    $nbMembres
                ),
                default => $this->addHtmlContent(
                    $contenu,
                    $contenusParSection->get($typeSection->id)?->contenu
                ),
            };
        }

        // Fallback si aucune section définie (projet sans TypeProjet configuré)
        if (! $aContenu) {
            $this->addHtmlContent($contenu, null);
        }

        // ─── Références (renvois / endnotes) ──────────────────────────────────
        $renvois = $projet->renvois ?? collect();

        if ($renvois->isNotEmpty()) {
            $contenu->addPageBreak();
            $contenu->addTitle('Références', 1);
            $contenu->addTextBreak(1);

            foreach ($renvois as $renvoi) {
                $texte = "{$renvoi->numero}.\t".($renvoi->contenu ?? '—');
                $contenu->addText(htmlspecialchars($texte), ['size' => 11]);
            }
        }

        // ─── Stream du fichier ────────────────────────────────────────────────
        $nomFichier = sprintf('projet_groupe_%d.docx', $groupe->id);

        return response()->streamDownload(function () use ($word) {
            // PhpWord génère les champs TOC avec deux bugs : PAGEREF utilise
            // l'ID numérique du signet au lieu de son nom (_TocN), et l'instruction
            // TOC omet les guillemets autour de la plage de niveaux.
            // On corrige le XML directement dans le .docx avant de l'envoyer.
            $temp = tempnam(sys_get_temp_dir(), 'phpword_').'.docx';

            try {
                $writer = IOFactory::createWriter($word, 'Word2007');
                $writer->save($temp);
                $this->fixTocXml($temp);
                readfile($temp);
            } finally {
                if (file_exists($temp)) {
                    unlink($temp);
                }
            }
        }, $nomFichier, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    /**
     * Crée une nouvelle section Word, avec ou sans pied de page numéroté.
     *
     * Le footer est omis sur la page titre et la table des matières,
     * de sorte que la numérotation n'apparaît qu'à partir de la première page de contenu.
     * Passer $debutNumerotation = 1 sur la première section de contenu pour redémarrer
     * le compteur à 1 (Word ignore les pages titre/TDM sans footer).
     *
     * @param  bool  $avecFooter  Ajouter le pied de page numéroté (défaut : true)
     * @param  int|null  $debutNumerotation  Valeur de départ du compteur de pages (null = continuer)
     */
    private function newSection(PhpWord $word, bool $avecFooter = true, ?int $debutNumerotation = null): Section
    {
        $settings = $debutNumerotation !== null ? ['pageNumberingStart' => $debutNumerotation] : [];
        $section = $word->addSection($settings);

        if ($avecFooter) {
            $footer = $section->addFooter();
            $footer->addPreserveText(
                '{PAGE}',
                ['size' => 9, 'color' => '444444'],
                ['alignment' => Jc::RIGHT],
            );
        }

        return $section;
    }

    /**
     * Ajoute les paragraphes d'une section de type 'paragraphes' comme sous-sections (Heading 2).
     * Tous les paragraphes partagent la même section Word pour éviter les sauts de page inutiles.
     *
     * @param  Collection  $paragraphes
     */
    private function renderParagraphes(Section $section, $paragraphes): void
    {
        if ($paragraphes->isEmpty()) {
            $this->addHtmlContent($section, null);

            return;
        }

        foreach ($paragraphes->sortBy('ordre') as $paragraphe) {
            $titre = $paragraphe->titre ?: "Paragraphe {$paragraphe->ordre}";
            $section->addTitle($titre, 2);
            $section->addTextBreak(1);
            $this->addHtmlContent($section, $paragraphe->contenu);
        }
    }

    /**
     * Ajoute les conclusions individuelles d'une section de type 'individuel'.
     * Tous les membres partagent la même section Word pour éviter les sauts de page inutiles.
     *
     * @param  Collection  $membres
     * @param  Collection  $conclusionsParUser  keyBy user_id
     */
    private function renderIndividuel(Section $section, $membres, $conclusionsParUser, int $nbMembres): void
    {
        foreach ($membres as $membre) {
            /** @var User $membre */
            if ($nbMembres > 1) {
                $section->addTitle("{$membre->prenom} {$membre->nom}", 2);
                $section->addTextBreak(1);
            }

            $conclusion = $conclusionsParUser->get($membre->id);
            $this->addHtmlContent($section, $conclusion?->contenu);
        }
    }

    /**
     * Corrige les deux bugs de PhpWord dans le XML de la table des matières.
     *
     * Bug 1 — PhpWord génère `PAGEREF 0 \h` mais les signets sont nommés `_Toc0`.
     *          Word ne trouve pas les signets et affiche « 1 » pour tous les numéros.
     * Bug 2 — L'instruction TOC omet les guillemets : `\o 1-2` au lieu de `\o "1-2"`,
     *          ce qui empêche certaines versions de Word d'interpréter correctement le champ.
     *
     * @param  string  $filePath  Chemin absolu du fichier .docx temporaire
     */
    private function fixTocXml(string $filePath): void
    {
        $zip = new \ZipArchive;
        if ($zip->open($filePath) !== true) {
            return;
        }

        $xml = $zip->getFromName('word/document.xml');
        if ($xml === false) {
            $zip->close();

            return;
        }

        // Bug 1 : PAGEREF 0 \h → PAGEREF _Toc0 \h
        $xml = preg_replace('/PAGEREF (\d+) \\\\h/', 'PAGEREF _Toc$1 \\h', $xml);

        // Bug 2 : \o 1-2 → \o "1-2"
        $xml = preg_replace('/TOC \\\\o (\d+-\d+) /', 'TOC \\o "$1" ', $xml);

        $zip->addFromString('word/document.xml', $xml);
        $zip->close();
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
