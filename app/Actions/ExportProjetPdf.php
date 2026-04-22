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

        // Noms des membres pour la page titre — chacun sur sa propre ligne dans la vue
        $membres = $groupe->membres->map(fn ($m) => "{$m->prenom} {$m->nom}")->values();

        // Charger les sections dynamiques si le projet a un type de projet
        if (! $projet->relationLoaded('typeProjet')) {
            $projet->load(['typeProjet.sections', 'sectionContenus']);
        }

        $sections = $projet->typeProjet?->sections ?? collect();
        $contenusParSection = $projet->sectionContenus->keyBy('section_id');

        $sectionsAvecContenu = $sections->map(fn ($s) => [
            'label' => $s->label,
            'contenu' => HtmlHelper::stripAnnotationMarks($contenusParSection->get($s->id)?->contenu),
        ])->values();

        $typeProjet = $projet->typeProjet;

        $pdf = Pdf::loadView('projets.export', [
            'projet' => $projet,
            'groupe' => $groupe,
            'classe' => $cours,
            'enseignant' => $enseignant,
            'membres' => $membres,
            'sections' => $sectionsAvecContenu,
            'renvois' => $projet->renvois ?? collect(),
            'genererPageTitre' => $typeProjet?->generer_page_titre ?? true,
            'genererTableMatieres' => $typeProjet?->generer_table_matieres ?? true,
            'pageTitreContenu' => $projet->page_titre_contenu,
            'tableMatieresContenu' => $projet->table_matieres_contenu,
            // Les conclusions sont chargées via $projet->conclusions (relation)
            // Closure exposée à la vue Blade pour nettoyer les marques d'annotation
            'stripMarks' => fn (?string $html): string => HtmlHelper::stripAnnotationMarks($html),
        ])->setPaper('a4', 'portrait');

        $nomFichier = sprintf('projet_groupe_%d.pdf', $groupe->numero);

        return $pdf->download($nomFichier);
    }
}
