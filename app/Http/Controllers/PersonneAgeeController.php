<?php

namespace App\Http\Controllers;

use App\Models\Classe;
use Inertia\Inertia;
use Inertia\Response;

class PersonneAgeeController extends Controller
{
    /**
     * Affiche la page d'accueil de la personne âgée avec ses classes assignées.
     */
    public function index(): Response
    {
        $user = auth()->user();

        $classes = Classe::where('personne_agee_id', $user->id)
            ->with(['cours:id,nom_cours,code', 'membres:id,prenom,nom'])
            ->withCount('echanges')
            ->get()
            ->map(fn (Classe $classe) => [
                'id' => $classe->id,
                'numero' => $classe->numero,
                'cours' => $classe->cours,
                'membres' => $classe->membres,
                'nb_echanges' => $classe->echanges_count,
            ]);

        return Inertia::render('PersonneAgee/Index', [
            'classes' => $classes,
        ]);
    }
}
