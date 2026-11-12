<?php

namespace App\Http\Controllers\Cours;

use App\Http\Controllers\Controller;
use App\Services\TableauBordEtudiantProps;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tableau de bord de l'étudiant : ses cours, projets et visioconférences.
 */
class TableauBordEtudiantController extends Controller
{
    /**
     * Affiche les cours de l'étudiant authentifié, ses projets, ses prochaines visios,
     * ses références personnelles et sa config Zotero.
     */
    public function __invoke(TableauBordEtudiantProps $tableauBordEtudiantProps): Response
    {
        return Inertia::render('Cours/Index', $tableauBordEtudiantProps->pourEtudiant(auth()->user()));
    }
}
