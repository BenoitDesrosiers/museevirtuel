<?php

namespace App\Actions;

use App\Helpers\HtmlHelper;
use App\Models\Groupe;
use App\Models\ProjetRecherche;
use Barryvdh\DomPDF\Facade\Pdf;
use Dompdf\Cpdf;
use Dompdf\Dompdf;
use Illuminate\Http\Response;

class ExportProjetPdf
{
    /**
     * Génère et retourne le projet de groupe en PDF.
     * Les conclusions sont individuelles (une par étudiant).
     */
    public function execute(ProjetRecherche $projet, Groupe $groupe): Response
    {
        $cours = $groupe->classe->cours;
        $enseignant = $cours->enseignant;

        // Membres : noms (pour page titre/TOC) + objets (pour sections individuel)
        $membresObjets = $groupe->membres;
        $membres = $membresObjets->map(fn ($m) => "{$m->prenom} {$m->nom}")->values();

        // Charger les sections dynamiques si le projet a un type de projet
        if (! $projet->relationLoaded('typeProjet')) {
            $projet->load(['typeProjet.sections', 'sectionContenus', 'sectionParagraphes', 'conclusions', 'renvois']);
        } elseif (! $projet->relationLoaded('renvois')) {
            $projet->load(['renvois']);
        }

        $sections = $projet->typeProjet?->sections ?? collect();
        $contenusParSection = $projet->sectionContenus->keyBy('section_id');
        $paragraphesParSection = $projet->sectionParagraphes->groupBy('section_id');

        // Conclusions liées à des sections (type individuel), indexées par section_id puis user_id
        $conclusionsParSection = $projet->conclusions
            ->filter(fn ($c) => $c->section_id !== null)
            ->groupBy('section_id')
            ->map(fn ($concs) => $concs->keyBy('user_id')
                ->map(fn ($c) => HtmlHelper::addRenvoisLinks(
                    HtmlHelper::stripAnnotationMarks($c->contenu)
                )));

        $sectionsAvecContenu = $sections->map(fn ($s) => [
            'label' => $s->label,
            'type' => $s->type ?? 'texte',
            'contenu' => HtmlHelper::addRenvoisLinks(
                HtmlHelper::stripAnnotationMarks($contenusParSection->get($s->id)?->contenu)
            ),
            'paragraphes' => ($paragraphesParSection->get($s->id) ?? collect())
                ->sortBy('ordre')
                ->map(fn ($p) => [
                    'titre' => $p->titre,
                    'contenu' => HtmlHelper::addRenvoisLinks(
                        HtmlHelper::stripAnnotationMarks($p->contenu)
                    ),
                ])
                ->values(),
            // Keyed by user_id → contenu HTML nettoyé (pour sections de type individuel)
            'membres_conclusions' => $conclusionsParSection->get($s->id) ?? collect(),
        ])->values();

        $typeProjet = $projet->typeProjet;

        $genererPageTitre = $typeProjet?->generer_page_titre ?? true;
        $genererTableMatieres = $typeProjet?->generer_table_matieres ?? true;

        // ─── Données communes aux deux passes de rendu ────────────────────────
        $viewData = [
            'projet' => $projet,
            'groupe' => $groupe,
            'classe' => $cours,
            'enseignant' => $enseignant,
            'membres' => $membres,
            'membresObjets' => $membresObjets,
            'sections' => $sectionsAvecContenu,
            'renvois' => $projet->renvois ?? collect(),
            'genererPageTitre' => $genererPageTitre,
            'genererTableMatieres' => $genererTableMatieres,
            'pageTitreContenu' => $projet->page_titre_contenu,
            'tableMatieresContenu' => $projet->table_matieres_contenu,
            // $stripMarks est utilisé dans le Blade pour les sections de l'ancien format
            // (intro, développements, conclusions standalone). On y ajoute aussi les liens de renvoi.
            'stripMarks' => fn (?string $html): string => HtmlHelper::addRenvoisLinks(
                HtmlHelper::stripAnnotationMarks($html)
            ),
        ];

        // ─── Numérotation conditionnelle ──────────────────────────────────────
        // La numérotation s'affiche uniquement à partir de la première page de contenu
        // (après la page titre et la table des matières). Les numéros sont continus :
        // titre = p.1 (masqué), TDM = p.2 (masqué), Introduction = p.3 (visible).
        $hasPageTitre = $genererPageTitre || ! empty($projet->page_titre_contenu);
        $hasTableMatieres = $genererTableMatieres || ! empty($projet->table_matieres_contenu);
        $pagesAIgnorer = ($hasPageTitre ? 1 : 0) + ($hasTableMatieres ? 1 : 0);

        // ─── Passe 1 : localisation des sections pour la TDM ─────────────────
        // Le document est rendu une première fois sans numéros de page dans la TDM.
        // Les destinations nommées (id="section-N", id="subsection-N-M", id="member-N-M")
        // permettent de retrouver la page réelle de chaque entrée via Cpdf.
        $sectionStructure = $sectionsAvecContenu->map(fn ($s) => [
            'type' => $s['type'],
            'subCount' => match ($s['type']) {
                'paragraphes' => $s['paragraphes']->count(),
                'individuel' => $membresObjets->count(),
                default => 0,
            },
        ])->values()->toArray();

        $tocPageNums = [];
        if ($sectionsAvecContenu->isNotEmpty() && $genererTableMatieres) {
            $pdfPass1 = Pdf::loadView('projets.export', $viewData + ['tocPageNums' => []])
                ->setPaper('a4', 'portrait');
            $pdfPass1->render();
            $tocPageNums = $this->extractTocPageNumbers(
                $pdfPass1->getDomPDF(),
                $sectionStructure,
                $pagesAIgnorer
            );
        }

        // ─── Passe 2 : rendu final avec vrais numéros de page dans la TDM ────
        $pdf = Pdf::loadView('projets.export', $viewData + ['tocPageNums' => $tocPageNums])
            ->setPaper('a4', 'portrait');

        $pdf->render();

        $canvas = $pdf->getDomPDF()->getCanvas();
        $canvas->page_script(function ($pageNumber, $pageCount, $canvas, $fontMetrics) use ($pagesAIgnorer): void {
            if ($pageNumber > $pagesAIgnorer) {
                $w = $canvas->get_width();
                $h = $canvas->get_height();
                $font = $fontMetrics->get_font('Times New Roman', 'normal');
                $canvas->text($w - 55, $h - 22, $pageNumber - $pagesAIgnorer, $font, 9, [0, 0, 0]);
            }
        });

        $nomFichier = sprintf('projet_groupe_%d.pdf', $groupe->numero);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$nomFichier.'"',
        ]);
    }

    /**
     * Extrait les numéros de page visibles de chaque section et sous-section après un premier rendu DomPDF.
     *
     * DomPDF 3 crée automatiquement une destination nommée pour tout élément HTML portant un attribut id.
     * On exploite Cpdf::$destinations (public) pour résoudre chaque ancre en numéro de page visible.
     *
     * Ancres attendues dans le Blade :
     *   - sections principales  : id="section-{i}"
     *   - sous-sections para    : id="subsection-{i}-{j}"
     *   - sous-sections individ : id="member-{i}-{j}"
     *
     * @param  array<int, array{type: string, subCount: int}>  $sectionStructure
     * @param  int  $pagesAIgnorer  Nombre de pages (titre + TDM) exclues de la numérotation visible.
     * @return array<string, int|string> Clé = ancre CSS (ex. "section-0"), valeur = numéro visible (ou '' si introuvable).
     */
    private function extractTocPageNumbers(Dompdf $dompdf, array $sectionStructure, int $pagesAIgnorer): array
    {
        $canvas = $dompdf->getCanvas();
        $cpdf = $canvas->get_cpdf();

        // Le nœud "pages" (objet #currentNode, toujours 3 par construction dans Cpdf::newDocument())
        // contient la liste ordonnée des IDs d'objets-pages du document.
        $allPageIds = $cpdf->objects[$cpdf->currentNode]['info']['pages'] ?? [];

        $pageNumbers = [];

        foreach ($sectionStructure as $i => $section) {
            $pageNumbers["section-{$i}"] = $this->pageForAnchor($cpdf, $allPageIds, "section-{$i}", $pagesAIgnorer);

            $prefix = $section['type'] === 'individuel' ? 'member' : 'subsection';

            for ($j = 0; $j < $section['subCount']; $j++) {
                $pageNumbers["{$prefix}-{$i}-{$j}"] = $this->pageForAnchor($cpdf, $allPageIds, "{$prefix}-{$i}-{$j}", $pagesAIgnorer);
            }
        }

        return $pageNumbers;
    }

    /**
     * Résout une ancre HTML en numéro de page visible via les internals publics de Cpdf.
     *
     * @param  array  $allPageIds  Liste ordonnée des IDs d'objets-pages (depuis Cpdf::$objects[$currentNode]).
     * @param  string  $anchor  Valeur de l'attribut id ciblé.
     * @param  int  $pagesAIgnorer  Décalage des pages non numérotées.
     * @return int|string Numéro de page visible, ou '' si l'ancre est introuvable.
     */
    private function pageForAnchor(Cpdf $cpdf, array $allPageIds, string $anchor, int $pagesAIgnorer): int|string
    {
        $destObjId = $cpdf->destinations[$anchor] ?? null;

        if ($destObjId === null) {
            return '';
        }

        $pageObjId = $cpdf->objects[$destObjId]['info']['page'] ?? null;

        if ($pageObjId === null) {
            return '';
        }

        $pageIndex = array_search($pageObjId, $allPageIds);

        if ($pageIndex === false) {
            return '';
        }

        return ($pageIndex + 1) - $pagesAIgnorer;
    }
}
