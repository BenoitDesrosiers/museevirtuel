<?php

namespace App\Http\Controllers;

use App\Actions\CreateEtudiantAction;
use App\Actions\ImportEtudiantsAction;
use App\Models\Cours;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CoursEtudiantController extends Controller
{
    public function __construct(
        private readonly CreateEtudiantAction $createEtudiant,
        private readonly ImportEtudiantsAction $importEtudiants,
    ) {}

    /**
     * Ajoute manuellement un étudiant au cours.
     *
     * Trouve ou crée l'étudiant via CreateEtudiantAction, puis l'attache au cours
     * si ce n'est pas déjà fait.
     */
    public function store(Request $request, Cours $cours): RedirectResponse
    {
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

        // Un étudiant ne peut appartenir qu'à un seul cours
        if ($etudiant->coursInscrits()->exists()) {
            return back()->withErrors(['no_da' => __('etudiant.already_in_class')]);
        }

        $cours->etudiants()->attach($etudiant->id, [
            'statut_cours' => $validated['statut_cours'] ?? null,
        ]);

        return back()->with('success', __('etudiant.added'));
    }

    /**
     * Met à jour les informations d'un étudiant dans le cours.
     */
    public function update(Request $request, Cours $cours, User $etudiant): RedirectResponse
    {
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

        $cours->etudiants()->updateExistingPivot($etudiant->id, [
            'statut_cours' => $validated['statut_cours'] ?? null,
        ]);

        return back()->with('success', __('etudiant.updated'));
    }

    /**
     * Retire un étudiant du cours (sans supprimer son compte).
     */
    public function destroy(Cours $cours, User $etudiant): RedirectResponse
    {
        $this->authorize('update', $cours);

        $cours->etudiants()->detach($etudiant->id);

        return back()->with('success', __('etudiant.removed'));
    }

    /**
     * Importe des étudiants depuis un fichier CSV dans le cours.
     */
    public function import(Request $request, Cours $cours): RedirectResponse
    {
        $this->authorize('update', $cours);

        $request->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $content = file_get_contents($request->file('csv')->getPathname());
        $created = $this->importEtudiants->execute($cours, $content);

        return back()->with('success', __('etudiant.imported', ['count' => $created]));
    }
}
