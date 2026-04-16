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
            ->with(['classe:id,cours_id', 'classe.cours:id,nom_cours,code', 'membres:id,prenom,nom'])
            ->withCount('echanges')
            ->get()
            ->map(fn (Groupe $groupe) => [
                'id' => $groupe->id,
                'code' => $groupe->code,
                'numero' => $groupe->numero,
                'cours_id' => $groupe->classe?->cours_id,
                'classe_id' => $groupe->classe_id,
                'cours' => $groupe->classe?->cours ? [
                    'id' => $groupe->classe->cours->id,
                    'nom_cours' => $groupe->classe->cours->nom_cours,
                    'code' => $groupe->classe->cours->code,
                ] : null,
                'membres' => $groupe->membres,
                'nb_echanges' => $groupe->echanges_count,
            ]);

        return Inertia::render('PersonneAgee/Index', [
            'groupes' => $groupes,
        ]);
    }
}
