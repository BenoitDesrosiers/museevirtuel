<?php

namespace App\Http\Controllers;

use App\Models\Groupe;
use Inertia\Inertia;
use Inertia\Response;

class PersonneAgeeController extends Controller
{
    /**
     * Affiche la page d'accueil de la personne âgée avec ses groupes assignés.
     */
    public function index(): Response
    {
        $user = auth()->user();

        $groupes = Groupe::where('personne_agee_id', $user->id)
            ->with(['classe:id,nom_cours,code', 'membres:id,prenom,nom'])
            ->withCount('echanges')
            ->get()
            ->map(fn (Groupe $groupe) => [
                'id' => $groupe->id,
                'numero' => $groupe->numero,
                'classe' => $groupe->classe,
                'membres' => $groupe->membres,
                'nb_echanges' => $groupe->echanges_count,
            ]);

        return Inertia::render('PersonneAgee/Index', [
            'groupes' => $groupes,
        ]);
    }
}
