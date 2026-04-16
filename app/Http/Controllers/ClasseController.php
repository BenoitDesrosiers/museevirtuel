<?php

namespace App\Http\Controllers;

use App\Models\Classe;
use App\Models\Cours;
use App\Models\EcheancierEtudiantProgress;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ClasseController extends Controller
{
    /**
     * Affiche le détail d'une classe (section) avec ses groupes.
     *
     * Accessible aux étudiants inscrits, à l'enseignant et aux admins.
     *
     * @throws AuthorizationException
     * @throws HttpException
     */
    /**
     * Liste les sections (classes) d'un cours dans lesquelles l'étudiant est inscrit.
     *
     * Réservé aux étudiants.
     */
    public function indexForStudent(Cours $cours): Response
    {
        $user = auth()->user();

        $classes = $cours->classes()
            ->whereHas('etudiants', fn ($q) => $q->where('users.id', $user->id))
            ->withCount('groupes')
            ->orderBy('numero')
            ->get(['id', 'numero', 'code', 'nom', 'jour_semaine', 'plage_horaire', 'cours_id']);

        $etapes = $cours->echeancierEtapes()->get();

        $progressions = EcheancierEtudiantProgress::whereIn('echeancier_etape_id', $etapes->pluck('id'))
            ->where('user_id', $user->id)
            ->pluck('is_done', 'echeancier_etape_id');

        $echeancierEtapes = $etapes->map(fn ($etape) => [
            'id' => $etape->id,
            'semaine' => $etape->semaine,
            'etape' => $etape->etape,
            'is_done' => $etape->is_done,
            'ordre' => $etape->ordre,
            'etudiant_done' => (bool) ($progressions[$etape->id] ?? false),
        ]);

        return Inertia::render('Cours/Classes', [
            'cours' => $cours->only('id', 'nom_cours', 'code', 'groupe'),
            'classes' => $classes,
            'echeancierEtapes' => $echeancierEtapes,
        ]);
    }

    /**
     * Affiche le détail d'une classe (section) avec ses groupes.
     *
     * Accessible aux étudiants inscrits, à l'enseignant et aux admins.
     *
     * @throws AuthorizationException
     * @throws HttpException
     */
    public function show(Cours $cours, Classe $classe): Response
    {
        abort_if($classe->cours_id !== $cours->id, 404);

        $classe->load('cours');
        $this->authorize('view', $classe);

        $user = auth()->user();
        $estEnseignant = $cours->enseignant_id === $user->id;

        $classe->load([
            'groupes.membres',
            'groupes.thematiques',
            'groupes.temoin',
            'etudiants' => fn ($query) => $query->withPivot('statut_cours'),
        ]);

        return Inertia::render('Classes/Show', [
            'cours' => $cours->only('id', 'nom_cours', 'code', 'groupe'),
            'classe' => $classe,
            'estEnseignant' => $estEnseignant,
        ]);
    }

    /**
     * Crée une nouvelle classe (section) dans un cours.
     *
     * Réservé à l'enseignant du cours et aux admins.
     *
     * @throws AuthorizationException
     */
    public function store(Request $request, Cours $cours): RedirectResponse
    {
        $this->authorize('update', $cours);

        $validated = $request->validate([
            'numero' => ['required', 'string', 'size:5', 'regex:/^\d{5}$/', Rule::unique('classes')->where(fn ($q) => $q->where('cours_id', $cours->id))],
            'nom' => ['nullable', 'string', 'max:100'],
            'jour_semaine' => ['nullable', 'string', 'max:20'],
            'plage_horaire' => ['nullable', 'string', 'max:50'],
        ]);

        Classe::create(array_merge(
            ['cours_id' => $cours->id, 'code' => $cours->code],
            array_filter($validated, fn ($v) => $v !== null)
        ));

        return back()->with('success', 'Classe créée.');
    }

    /**
     * Met à jour une classe (section) : code et/ou nom.
     *
     * Réservé à l'enseignant du cours et aux admins.
     *
     * @throws AuthorizationException
     * @throws HttpException
     */
    public function update(Request $request, Cours $cours, Classe $classe): RedirectResponse
    {
        abort_if($classe->cours_id !== $cours->id, 404);
        $this->authorize('update', $classe);

        $validated = $request->validate([
            'numero' => ['required', 'string', 'size:5', 'regex:/^\d{5}$/', Rule::unique('classes')->where(fn ($q) => $q->where('cours_id', $cours->id))->ignore($classe->id)],
            'nom' => ['nullable', 'string', 'max:100'],
            'jour_semaine' => ['nullable', 'string', 'max:20'],
            'plage_horaire' => ['nullable', 'string', 'max:50'],
        ]);

        $classe->update(array_merge($validated, ['code' => $cours->code]));

        return back()->with('success', 'Classe mise à jour.');
    }

    /**
     * Supprime une classe (section) — cascade sur les groupes et projets.
     *
     * Réservé à l'enseignant du cours et aux admins.
     *
     * @throws AuthorizationException
     * @throws HttpException
     */
    public function destroy(Cours $cours, Classe $classe): RedirectResponse
    {
        abort_if($classe->cours_id !== $cours->id, 404);

        $classe->load('cours');
        $this->authorize('delete', $classe);

        $classe->delete();

        return redirect()->route('cours.show', $cours)->with('success', 'Classe supprimée.');
    }
}
