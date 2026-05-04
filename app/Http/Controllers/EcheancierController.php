<?php

namespace App\Http\Controllers;

use App\Models\Cours;
use App\Models\EcheancierEtape;
use App\Models\EcheancierEtudiantProgress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EcheancierController extends Controller
{
    /**
     * Ajoute une nouvelle étape à l'échéancier d'un cours.
     *
     * Réservé à l'enseignant du cours et aux admins.
     */
    public function store(Request $request, Cours $cours): RedirectResponse
    {
        Gate::authorize('update', $cours);

        $validated = $request->validate([
            'semaine' => ['required', 'integer', 'min:1', 'max:15'],
            'periode' => ['nullable', 'integer', 'in:1,2'],
            'etape' => ['required', 'string', 'max:500'],
        ]);

        // L'ordre est défini comme le prochain dans la semaine
        $ordre = EcheancierEtape::where('cours_id', $cours->id)
            ->where('semaine', $validated['semaine'])
            ->max('ordre') ?? -1;

        EcheancierEtape::create([
            'cours_id' => $cours->id,
            'semaine' => $validated['semaine'],
            'periode' => $validated['periode'] ?? null,
            'etape' => $validated['etape'],
            'is_done' => false,
            'ordre' => $ordre + 1,
        ]);

        return back();
    }

    /**
     * Met à jour le texte d'une étape de l'échéancier.
     *
     * Réservé à l'enseignant du cours et aux admins.
     */
    public function update(Request $request, Cours $cours, EcheancierEtape $etape): RedirectResponse
    {
        abort_if($etape->cours_id !== $cours->id, 404);
        Gate::authorize('update', $cours);

        $validated = $request->validate([
            'etape' => ['required', 'string', 'max:500'],
            'periode' => ['nullable', 'integer', 'in:1,2'],
        ]);

        $etape->update($validated);

        return back();
    }

    /**
     * Supprime une étape de l'échéancier.
     *
     * Réservé à l'enseignant du cours et aux admins.
     */
    public function destroy(Cours $cours, EcheancierEtape $etape): RedirectResponse
    {
        abort_if($etape->cours_id !== $cours->id, 404);
        Gate::authorize('update', $cours);

        $etape->delete();

        return back();
    }

    /**
     * Supprime toutes les étapes de l'échéancier d'un cours.
     *
     * Réservé à l'enseignant du cours et aux admins.
     */
    public function destroyAll(Cours $cours): RedirectResponse
    {
        Gate::authorize('update', $cours);

        $cours->echeancierEtapes()->delete();

        return back();
    }

    /**
     * Bascule l'état is_done d'une étape (fait / non fait).
     *
     * Réservé à l'enseignant du cours et aux admins.
     */
    public function toggleDone(Cours $cours, EcheancierEtape $etape): RedirectResponse
    {
        abort_if($etape->cours_id !== $cours->id, 404);
        Gate::authorize('update', $cours);

        $etape->update(['is_done' => ! $etape->is_done]);

        return back();
    }

    /**
     * Bascule la progression personnelle de l'étudiant connecté pour une étape.
     *
     * Chaque étudiant inscrit au cours gère uniquement son propre avancement.
     */
    public function toggleEtudiant(Cours $cours, EcheancierEtape $etape): RedirectResponse
    {
        abort_if($etape->cours_id !== $cours->id, 404);

        $user = auth()->user();

        // Vérification d'inscription au cours via une section (classe_etudiant)
        abort_unless(
            $cours->classes()->whereHas('etudiants', fn ($q) => $q->where('users.id', $user->id))->exists(),
            403
        );

        $progression = EcheancierEtudiantProgress::firstOrCreate(
            ['echeancier_etape_id' => $etape->id, 'user_id' => $user->id],
            ['is_done' => false],
        );

        $progression->update(['is_done' => ! $progression->is_done]);

        return back();
    }
}
