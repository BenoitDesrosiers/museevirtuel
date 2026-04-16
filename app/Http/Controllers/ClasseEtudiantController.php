<?php

namespace App\Http\Controllers;

use App\Actions\CreateEtudiantAction;
use App\Actions\ImportEtudiantsAction;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClasseEtudiantController extends Controller
{
    public function __construct(
        private readonly CreateEtudiantAction $createEtudiant,
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
     */
    public function store(Request $request, Cours $cours, Classe $classe): RedirectResponse
    {
        $this->assertClasseAppartientAuCours($classe, $cours);
        $this->authorize('update', $cours);

        $validated = $request->validate([
            'prenom' => ['required', 'string', 'max:255'],
            'nom' => ['required', 'string', 'max:255'],
            'no_da' => ['required', 'string', 'max:20'],
            'statut_cours' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
        ]);

        $etudiant = $this->createEtudiant->execute(
            $validated['no_da'],
            $validated['prenom'],
            $validated['nom'],
            $validated['email'] ?? null,
        );

        if ($etudiant->classesInscrites()->exists()) {
            return back()->withErrors(['no_da' => __('etudiant.already_in_class')]);
        }

        $classe->etudiants()->attach($etudiant->id, [
            'statut_cours' => $validated['statut_cours'] ?? null,
        ]);

        return back()->with('success', __('etudiant.added'));
    }

    /**
     * Met à jour les informations d'un étudiant inscrit dans une section.
     */
    public function update(Request $request, Cours $cours, Classe $classe, User $etudiant): RedirectResponse
    {
        $this->assertClasseAppartientAuCours($classe, $cours);
        $this->authorize('update', $cours);

        $validated = $request->validate([
            'prenom' => ['required', 'string', 'max:255'],
            'nom' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)->ignore($etudiant->id)],
            'no_da' => ['required', 'string', 'max:20'],
            'statut_cours' => ['nullable', 'string', 'max:100'],
        ]);

        $etudiant->update([
            'prenom' => $validated['prenom'],
            'nom' => $validated['nom'],
            'email' => $validated['email'],
            'no_da' => $validated['no_da'],
        ]);

        $classe->etudiants()->updateExistingPivot($etudiant->id, [
            'statut_cours' => $validated['statut_cours'] ?? null,
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
     */
    public function import(Request $request, Cours $cours, Classe $classe): RedirectResponse
    {
        $this->assertClasseAppartientAuCours($classe, $cours);
        $this->authorize('update', $cours);

        $request->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $content = file_get_contents($request->file('csv')->getPathname());
        $created = $this->importEtudiants->execute($classe, $content);

        return back()->with('success', __('etudiant.imported', ['count' => $created]));
    }
}
