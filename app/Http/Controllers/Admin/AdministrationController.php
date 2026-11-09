<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cours;
use App\Models\Etablissement;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class AdministrationController extends Controller
{
    /**
     * Affiche le tableau de bord d'administration avec la liste des enseignants et les statistiques.
     */
    public function index(): Response
    {
        $enseignants = User::where('role', 'enseignant')
            ->withCount(['cours', 'thematiques'])
            ->with('etablissement:id,nom')
            ->orderBy('nom')
            ->get();

        $stats = [
            'total_enseignants' => User::where('role', 'enseignant')->count(),
            'total_classes' => Cours::count(),
            'total_etudiants' => User::where('role', 'etudiant')->count(),
        ];

        $temoinsEnAttente = User::enAttente()
            ->where('role', 'personne_agee')
            ->with('thematiquesChoisies:id,nom')
            ->with('etablissementsChoisis')
            ->orderBy('created_at')
            ->get(['id', 'prenom', 'nom', 'email', 'description', 'created_at'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'prenom' => $u->prenom,
                'nom' => $u->nom,
                'email' => $u->email,
                'description' => $u->description,
                'created_at' => $u->created_at,
                'thematiques_choisies' => $u->thematiquesChoisies->map->only('id', 'nom')->values(),
                'theme_libre' => $u->themeLibre(),
            ]);

        $etablissements = Etablissement::withCount(['enseignants', 'thematiques'])
            ->orderBy('nom')
            ->get();

        return Inertia::render('Administration/Index', [
            'enseignants' => $enseignants,
            'stats' => $stats,
            'temoinsEnAttente' => $temoinsEnAttente,
            'etablissements' => $etablissements,
        ]);
    }
}
