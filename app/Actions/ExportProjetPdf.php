<?php

namespace App\Actions;

use App\Helpers\HtmlHelper;
use App\Models\Groupe;
use App\Models\ProjetRecherche;
use Barryvdh\DomPDF\Facade\Pdf;
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
            $projet->load(['typeProjet.sections', 'sectionContenus', 'sectionParagraphes', 'conclusions']);
        }

        $sections = $projet->typeProjet?->sections ?? collect();
        $contenusParSection = $projet->sectionContenus->keyBy('section_id');
        $paragraphesParSection = $projet->sectionParagraphes->groupBy('section_id');

        // Conclusions liées à des sections (type individuel), indexées par section_id puis user_id
        $conclusionsParSection = $projet->conclusions
            ->filter(fn ($c) => $c->section_id !== null)
            ->groupBy('section_id')
            ->map(fn ($concs) => $concs->keyBy('user_id')
                ->map(fn ($c) => HtmlHelper::stripAnnotationMarks($c->contenu)));

        $sectionsAvecContenu = $sections->map(fn ($s) => [
            'label' => $s->label,
            'type' => $s->type ?? 'texte',
            'contenu' => HtmlHelper::stripAnnotationMarks($contenusParSection->get($s->id)?->contenu),
            'paragraphes' => ($paragraphesParSection->get($s->id) ?? collect())
                ->sortBy('ordre')
                ->map(fn ($p) => [
                    'titre' => $p->titre,
                    'contenu' => HtmlHelper::stripAnnotationMarks($p->contenu),
                ])
                ->values(),
            // Keyed by user_id → contenu HTML nettoyé (pour sections de type individuel)
            'membres_conclusions' => $conclusionsParSection->get($s->id) ?? collect(),
        ])->values();

        $typeProjet = $projet->typeProjet;

        $pdf = Pdf::loadView('projets.export', [
            'projet' => $projet,
            'groupe' => $groupe,
            'classe' => $cours,
            'enseignant' => $enseignant,
            'membres' => $membres,
            'membresObjets' => $membresObjets,
            'sections' => $sectionsAvecContenu,
            'renvois' => $projet->renvois ?? collect(),
            'genererPageTitre' => $typeProjet?->generer_page_titre ?? true,
            'genererTableMatieres' => $typeProjet?->generer_table_matieres ?? true,
            'pageTitreContenu' => $projet->page_titre_contenu,
            'tableMatieresContenu' => $projet->table_matieres_contenu,
            'stripMarks' => fn (?string $html): string => HtmlHelper::stripAnnotationMarks($html),
        ])->setPaper('a4', 'portrait');

        $nomFichier = sprintf('projet_groupe_%d.pdf', $groupe->numero);

        return $pdf->download($nomFichier);
    }
}
