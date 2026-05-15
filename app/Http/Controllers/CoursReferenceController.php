<?php

namespace App\Http\Controllers;

use App\Models\Cours;
use App\Models\CoursReference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CoursReferenceController extends Controller
{
    /**
     * Ajoute une référence bibliographique à un cours.
     *
     * Réservé à l'enseignant du cours et aux admins.
     */
    public function store(Request $request, Cours $cours): RedirectResponse
    {
        Gate::authorize('update', $cours);

        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'url' => ['nullable', 'url', 'max:500'],
        ]);

        $ordre = ($cours->references()->max('ordre') ?? 0) + 1;

        $cours->references()->create([
            'nom' => $validated['nom'],
            'url' => $validated['url'] ?? null,
            'ordre' => $ordre,
        ]);

        return back()->with('success', 'Référence ajoutée.');
    }

    /**
     * Met à jour une référence bibliographique.
     *
     * Réservé à l'enseignant du cours et aux admins.
     */
    public function update(Request $request, Cours $cours, CoursReference $reference): RedirectResponse
    {
        Gate::authorize('update', $cours);
        abort_if($reference->cours_id !== $cours->id, 404);

        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'url' => ['nullable', 'url', 'max:500'],
        ]);

        $reference->update($validated);

        return back()->with('success', 'Référence mise à jour.');
    }

    /**
     * Supprime une référence bibliographique et renumérotise les suivantes.
     *
     * Réservé à l'enseignant du cours et aux admins.
     */
    public function destroy(Cours $cours, CoursReference $reference): RedirectResponse
    {
        Gate::authorize('update', $cours);
        abort_if($reference->cours_id !== $cours->id, 404);

        $reference->delete();

        // Renuméroter pour éviter les trous
        $cours->references()->orderBy('ordre')->each(
            function (CoursReference $r, int $index): void {
                $r->update(['ordre' => $index + 1]);
            }
        );

        return back()->with('success', 'Référence supprimée.');
    }

    /**
     * Réordonne les références bibliographiques d'un cours.
     *
     * Reçoit un tableau d'IDs dans l'ordre désiré.
     */
    public function reorder(Request $request, Cours $cours): RedirectResponse
    {
        Gate::authorize('update', $cours);

        $validated = $request->validate([
            'ordre' => ['required', 'array'],
            'ordre.*' => ['required', 'integer'],
        ]);

        foreach ($validated['ordre'] as $index => $referenceId) {
            CoursReference::where('id', $referenceId)
                ->where('cours_id', $cours->id)
                ->update(['ordre' => $index + 1]);
        }

        return back();
    }
}
