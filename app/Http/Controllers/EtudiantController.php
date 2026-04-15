<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class EtudiantController extends Controller
{
    /**
     * Redirige l'étudiant vers la liste de ses classes.
     */
    public function index(): RedirectResponse
    {
        return to_route('cours.index');
    }
}
