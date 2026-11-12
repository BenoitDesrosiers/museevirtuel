<?php

namespace App\Http\Controllers\Groupe;

use App\Http\Controllers\Controller;
use App\Http\Requests\Groupe\StoreGroupeEchangeRequest;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\GroupeEchange;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class GroupeEchangeController extends Controller
{
    /**
     * Affiche le fil d'échanges entre le groupe et le témoin assigné.
     *
     * Accessible aux membres, au témoin, à l'enseignant et aux admins.
     *
     * @throws AuthorizationException
     */
    public function index(Cours $cours, Classe $classe, Groupe $groupe): Response
    {
        $this->autoriserEchange($cours, $classe, $groupe);

        $groupe->load(['temoin', 'membres', 'echanges.auteur']);

        return Inertia::render('Groupes/Echanges', [
            'cours' => $cours->only('id', 'nom_cours', 'code'),
            'classe' => $classe->only('id', 'code', 'cours_id'),
            'groupe' => $groupe->only('id', 'numero', 'temoin', 'membres'),
            'echanges' => $groupe->echanges,
        ]);
    }

    /**
     * Enregistre un nouveau message dans le fil d'échanges du groupe.
     *
     * Données validées par StoreGroupeEchangeRequest.
     *
     * @throws AuthorizationException
     */
    public function store(StoreGroupeEchangeRequest $request, Cours $cours, Classe $classe, Groupe $groupe): RedirectResponse
    {
        $this->autoriserEchange($cours, $classe, $groupe);

        $validated = $request->validated();

        GroupeEchange::create([
            'groupe_id' => $groupe->id,
            'auteur_id' => auth()->id(),
            'contenu' => $validated['contenu'],
        ]);

        return back()->with('success', __('groupe.echange_sent'));
    }

    /**
     * Vérifie que le groupe appartient à la classe/cours et autorise l'accès aux échanges.
     *
     * @throws AuthorizationException
     */
    private function autoriserEchange(Cours $cours, Classe $classe, Groupe $groupe): void
    {
        abort_if($classe->cours_id !== $cours->id, 404);
        abort_if($groupe->classe_id !== $classe->id, 404);
        $groupe->loadMissing('classe.cours');
        $this->authorize('echanges', $groupe);
    }
}
