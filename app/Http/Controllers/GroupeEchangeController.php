<?php

namespace App\Http\Controllers;

use App\Models\Classe;
use App\Models\Groupe;
use App\Models\GroupeEchange;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GroupeEchangeController extends Controller
{
    /**
     * Affiche le fil d'échanges entre le groupe et le témoin assigné.
     *
     * Accessible aux membres du groupe, au témoin, à l'enseignant et aux admins.
     *
     * @throws AuthorizationException
     */
    public function index(Classe $classe, Groupe $groupe): Response
    {
        $this->autoriserEchange($groupe, $classe);

        $groupe->load(['temoin', 'membres', 'echanges.auteur']);

        return Inertia::render('Groupes/Echanges', [
            'classe' => $classe->only('id', 'nom_cours', 'code'),
            'groupe' => $groupe->only('id', 'numero', 'temoin', 'membres'),
            'echanges' => $groupe->echanges,
        ]);
    }

    /**
     * Enregistre un nouveau message dans le fil d'échanges du groupe.
     *
     * Accessible aux membres du groupe, au témoin, à l'enseignant et aux admins.
     *
     * @throws AuthorizationException
     */
    public function store(Request $request, Classe $classe, Groupe $groupe): RedirectResponse
    {
        $this->autoriserEchange($groupe, $classe);

        $validated = $request->validate([
            'contenu' => ['required', 'string', 'max:3000'],
        ]);

        GroupeEchange::create([
            'groupe_id' => $groupe->id,
            'auteur_id' => auth()->id(),
            'contenu' => $validated['contenu'],
        ]);

        return back()->with('success', 'Message envoyé.');
    }

    /**
     * Vérifie que le groupe appartient à la classe et autorise l'accès aux échanges.
     *
     * @throws AuthorizationException
     */
    private function autoriserEchange(Groupe $groupe, Classe $classe): void
    {
        abort_if($groupe->classe_id !== $classe->id, 404);
        $groupe->loadMissing('classe');
        $this->authorize('echanges', $groupe);
    }
}
