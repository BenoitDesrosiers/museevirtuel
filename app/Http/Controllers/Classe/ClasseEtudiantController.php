<?php

namespace App\Http\Controllers\Classe;

use App\Actions\ImportEtudiantsAction;
use App\Actions\InscrireEtudiantClasseAction;
use App\Exceptions\TransactionEchoueeException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Classe\ImportClasseEtudiantRequest;
use App\Http\Requests\Classe\StoreClasseEtudiantRequest;
use App\Http\Requests\Classe\UpdateClasseEtudiantRequest;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class ClasseEtudiantController extends Controller
{
    public function __construct(
        private readonly InscrireEtudiantClasseAction $inscrireEtudiantClasseAction,
        private readonly ImportEtudiantsAction $importEtudiants,
    ) {}

    /**
     * Vérifie que la classe appartient bien au cours donné.
     */
    private function assertClasseAppartientAuCours(Classe $classe, Cours $cours): void
    {
        abort_if($classe->cours_id !== $cours->id, 404);
    }

    /**
     * Ajoute manuellement un étudiant dans une section.
     *
     * Les données (statut « actif » par défaut, courriel non pris par un autre compte,
     * étudiant pas encore inscrit) sont validées par StoreClasseEtudiantRequest.
     */
    public function store(StoreClasseEtudiantRequest $request, Cours $cours, Classe $classe): RedirectResponse
    {
        try {
            $this->inscrireEtudiantClasseAction->execute($classe, $request->validated());
        } catch (TransactionEchoueeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('etudiant.added'));
    }

    /**
     * Met à jour les informations d'un étudiant inscrit dans une section.
     *
     * Les données sont validées par UpdateClasseEtudiantRequest.
     */
    public function update(UpdateClasseEtudiantRequest $request, Cours $cours, Classe $classe, User $etudiant): RedirectResponse
    {
        $validated = $request->validated();

        $etudiant->update([
            'prenom' => $validated['prenom'],
            'nom' => $validated['nom'],
            'email' => $validated['email'],
            'no_da' => $validated['no_da'],
        ]);

        $classe->etudiants()->updateExistingPivot($etudiant->id, [
            'statut_cours' => $validated['statut_cours'],
        ]);

        return back()->with('success', __('etudiant.updated'));
    }

    /**
     * Retire un étudiant de la section sans supprimer son compte.
     */
    public function destroy(Cours $cours, Classe $classe, User $etudiant): RedirectResponse
    {
        $this->assertClasseAppartientAuCours($classe, $cours);
        $this->authorize('update', $cours);

        $classe->etudiants()->detach($etudiant->id);

        return back()->with('success', __('etudiant.removed'));
    }

    /**
     * Importe des étudiants CSV dans la section.
     *
     * Le fichier est validé par ImportClasseEtudiantRequest.
     */
    public function import(ImportClasseEtudiantRequest $request, Cours $cours, Classe $classe): RedirectResponse
    {
        $content = file_get_contents($request->file('csv')->getPathname());
        try {
            $created = $this->importEtudiants->execute($classe, $content);
        } catch (TransactionEchoueeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('etudiant.imported', ['count' => $created]));
    }
}
