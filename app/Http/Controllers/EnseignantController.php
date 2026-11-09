<?php

namespace App\Http\Controllers;

use App\Services\TableauBordEnseignantProps;
use Inertia\Inertia;
use Inertia\Response;

class EnseignantController extends Controller
{
    /**
     * Affiche le tableau de bord de l'enseignant avec ses classes, thématiques,
     * les travaux récemment remis et les témoins en attente liés à ses thématiques.
     */
    public function index(TableauBordEnseignantProps $tableauBordEnseignantProps): Response
    {
        return Inertia::render('Enseignant/Index',
            $tableauBordEnseignantProps->pourEnseignant(auth()->user()));
    }
}
