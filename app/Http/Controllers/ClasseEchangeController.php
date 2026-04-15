<?php

namespace App\Http\Controllers;

use App\Models\Classe;
use App\Models\ClasseEchange;
use App\Models\Cours;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClasseEchangeController extends Controller
{
    /**
     * Affiche le fil d'échanges entre la classe et le témoin assigné.
     *
     * Accessible aux membres, au témoin, à l'enseignant et aux admins.
     *
     * @throws AuthorizationException
     */
    public function index(Cours $cours, Classe $classe): Response
    {
        $this->autoriserEchange($classe, $cours);

        $classe->load(['temoin', 'membres', 'echanges.auteur']);

        return Inertia::render('Classes/Echanges', [
            'cours' => $cours->only('id', 'nom_cours', 'code'),
            'classe' => $classe->only('id', 'numero', 'temoin', 'membres'),
            'echanges' => $classe->echanges,
        ]);
    }

    /**
     * Enregistre un nouveau message dans le fil d'échanges de la classe.
     *
     * @throws AuthorizationException
     */
    public function store(Request $request, Cours $cours, Classe $classe): RedirectResponse
    {
        $this->autoriserEchange($classe, $cours);

        $validated = $request->validate([
            'contenu' => ['required', 'string', 'max:3000'],
        ]);

        ClasseEchange::create([
            'classe_id' => $classe->id,
            'auteur_id' => auth()->id(),
            'contenu' => $validated['contenu'],
        ]);

        return back()->with('success', 'Message envoyé.');
    }

    /**
     * Vérifie que la classe appartient au cours et autorise l'accès aux échanges.
     *
     * @throws AuthorizationException
     */
    private function autoriserEchange(Classe $classe, Cours $cours): void
    {
        abort_if($classe->cours_id !== $cours->id, 404);
        $classe->loadMissing('cours');
        $this->authorize('echanges', $classe);
    }
}
