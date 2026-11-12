<?php

namespace App\Actions;

use App\Helpers\HtmlHelper;
use App\Models\Groupe;
use App\Models\ProjetRecherche;
use Barryvdh\DomPDF\Facade\Pdf;
use Dompdf\Cpdf;
use Dompdf\Dompdf;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class ExportProjetPdf
{
    /**
     * Génère et retourne le projet de groupe en PDF.
     * Les conclusions sont individuelles (une par étudiant).
     */
    public function execute(ProjetRecherche $projet, Groupe $groupe): Response
    {
        // Charger les sections dynamiques si le projet a un type de projet
        if (! $projet->relationLoaded('typeProjet')) {
            $projet->load(['typeProjet.sections', 'sectionContenus', 'sectionParagraphes',
                'conclusions', 'renvois']);
        } elseif (! $projet->relationLoaded('renvois')) {
            $projet->load(['renvois']);
        }

        $viewData = $this->donneesVue($projet, $groupe);
        $pagesAIgnorer = $this->pagesAIgnorer($projet, $viewData);
        $tocPageNums = $this->numerosTableMatieres($viewData, $pagesAIgnorer);

        // Passe 2 : rendu final avec les vrais numéros de page dans la TDM
        $pdf = Pdf::loadView('projets.export',
            $viewData + ['tocPageNums' => $tocPageNums])
            ->setPaper('a4', 'portrait');
        $pdf->render();
        $this->numeroterPages($pdf->getDomPDF(), $pagesAIgnorer);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.sprintf('projet_groupe_%d.pdf', $groupe->numero).'"',
        ]);
    }

    /**
     * Retourne les données communes aux deux passes de rendu de la vue projets.export.
     *
     * @return array<string, mixed>
     */
    private function donneesVue(ProjetRecherche $projet, Groupe $groupe): array
    {
        $cours = $groupe->classe->cours;

        return [
            'projet' => $projet,
            'groupe' => $groupe,
            'classe' => $cours,
            'enseignant' => $cours->enseignant,
            // Noms pour la page titre et la TDM ; objets pour les sections de type individuel
            'membres' => $groupe->membres->map(fn ($m) => "{$m->prenom} {$m->nom}")->values(),
            'membresObjets' => $groupe->membres,
            'sections' => $this->sections($projet),
            'renvois' => $projet->renvois ?? collect(),
            'genererPageTitre' => $projet->typeProjet?->generer_page_titre ?? true,
            'genererTableMatieres' => $projet->typeProjet?->generer_table_matieres ?? true,
            'pageTitreContenu' => $projet->page_titre_contenu,
            'tableMatieresContenu' => $projet->table_matieres_contenu,
            // Utilisé dans le Blade pour les sections de l'ancien format (intro, développements, conclusions standalone)
            'stripMarks' => $this->nettoyer(...),
        ];
    }

    /**
     * Retourne les sections du type de projet avec leur contenu nettoyé.
     *
     * Les conclusions des sections de type individuel sont indexées par user_id.
     */
    private function sections(ProjetRecherche $projet): Collection
    {
        $contenusParSection = $projet->sectionContenus->keyBy('section_id');
        $paragraphesParSection = $projet->sectionParagraphes->groupBy('section_id');
        $conclusionsParSection = $projet->conclusions
            ->filter(fn ($c) => $c->section_id !== null)
            ->groupBy('section_id')
            ->map(fn ($conclusions) => $conclusions->keyBy('user_id')->map(fn ($c) => $this->nettoyer($c->contenu)));

        return ($projet->typeProjet?->sections ?? collect())->map(fn ($s) => [
            'label' => $s->label,
            'type' => $s->type ?? 'texte',
            'contenu' => $this->nettoyer($contenusParSection->get($s->id)?->contenu),
            'paragraphes' => ($paragraphesParSection->get($s->id) ?? collect())
                ->sortBy('ordre')
                ->map(fn ($p) => ['titre' => $p->titre, 'contenu' => $this->nettoyer($p->contenu)])
                ->values(),
            'membres_conclusions' => $conclusionsParSection->get($s->id) ?? collect(),
        ])->values();
    }

    /**
     * Retire les marques d'annotation du HTML et y ajoute les liens de renvoi.
     */
    private function nettoyer(?string $html): string
    {
        return HtmlHelper::addRenvoisLinks(HtmlHelper::stripAnnotationMarks($html));
    }

    /**
     * Retourne le nombre de pages (titre, TDM) exclues de la numérotation visible.
     *
     * Les numéros restent continus : titre = p.1 (masqué), TDM = p.2 (masqué), contenu = p.3 (affiché 1).
     *
     * @param  array<string, mixed>  $viewData
     */
    private function pagesAIgnorer(ProjetRecherche $projet, array $viewData): int
    {
        $hasPageTitre = $viewData['genererPageTitre'] || ! empty($projet->page_titre_contenu);
        $hasTableMatieres = $viewData['genererTableMatieres'] || ! empty($projet->table_matieres_contenu);

        return ($hasPageTitre ? 1 : 0) + ($hasTableMatieres ? 1 : 0);
    }

    /**
     * Passe 1 : rend le document une première fois pour localiser la page de chaque entrée de la TDM.
     *
     * Les destinations nommées (id="section-N", "subsection-N-M", "member-N-M") donnent la page réelle via Cpdf.
     * Aucun rendu supplémentaire si la TDM n'est pas générée ou s'il n'y a aucune section.
     *
     * @param  array<string, mixed>  $viewData
     * @return array<string, int|string>
     */
    private function numerosTableMatieres(array $viewData, int $pagesAIgnorer): array
    {
        if ($viewData['sections']->isEmpty() || ! $viewData['genererTableMatieres']) {
            return [];
        }

        $sectionStructure = $viewData['sections']->map(fn ($s) => [
            'type' => $s['type'],
            'subCount' => match ($s['type']) {
                'paragraphes' => $s['paragraphes']->count(),
                'individuel' => $viewData['membresObjets']->count(),
                default => 0,
            },
        ])->values()->toArray();

        $pdfPass1 = Pdf::loadView('projets.export', $viewData + ['tocPageNums' => []])
            ->setPaper('a4', 'portrait');
        $pdfPass1->render();

        return $this->extractTocPageNumbers($pdfPass1->getDomPDF(), $sectionStructure, $pagesAIgnorer);
    }

    /**
     * Affiche le numéro de page en bas à droite, à partir de la première page de contenu.
     */
    private function numeroterPages(Dompdf $dompdf, int $pagesAIgnorer): void
    {
        $dompdf->getCanvas()->page_script(function ($pageNumber, $pageCount, $canvas, $fontMetrics) use ($pagesAIgnorer): void {
            if ($pageNumber > $pagesAIgnorer) {
                $font = $fontMetrics->get_font('Times New Roman', 'normal');
                $canvas->text($canvas->get_width() - 55, $canvas->get_height() - 22, $pageNumber - $pagesAIgnorer, $font, 9, [0, 0, 0]);
            }
        });
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
