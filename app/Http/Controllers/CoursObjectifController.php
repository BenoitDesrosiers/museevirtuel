<?php

namespace App\Http\Controllers;

use App\Models\Cours;
use App\Models\CoursObjectif;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CoursObjectifController extends Controller
{
    /**
     * Ajoute un objectif pédagogique à un cours.
     *
     * Réservé à l'enseignant du cours et aux admins.
     */
    public function store(Request $request, Cours $cours): RedirectResponse
    {
        Gate::authorize('update', $cours);

        $validated = $request->validate([
            'contenu' => ['required', 'string', 'max:1000'],
        ]);

        $ordre = ($cours->objectifs()->max('ordre') ?? 0) + 1;

        $cours->objectifs()->create([
            'contenu' => $validated['contenu'],
            'ordre' => $ordre,
        ]);

        return back()->with('success', 'Objectif ajouté.');
    }

    /**
     * Met à jour le contenu d'un objectif pédagogique.
     *
     * Réservé à l'enseignant du cours et aux admins.
     */
    public function update(Request $request, Cours $cours, CoursObjectif $objectif): RedirectResponse
    {
        Gate::authorize('update', $cours);
        abort_if($objectif->cours_id !== $cours->id, 404);

        $validated = $request->validate([
            'contenu' => ['required', 'string', 'max:1000'],
        ]);

        $objectif->update($validated);

        return back()->with('success', 'Objectif mis à jour.');
    }

    /**
     * Supprime un objectif pédagogique et renumérotise les suivants.
     *
     * Réservé à l'enseignant du cours et aux admins.
     */
    public function destroy(Cours $cours, CoursObjectif $objectif): RedirectResponse
    {
        Gate::authorize('update', $cours);
        abort_if($objectif->cours_id !== $cours->id, 404);

        $objectif->delete();

        // Renuméroter pour éviter les trous
        $cours->objectifs()->orderBy('ordre')->each(
            function (CoursObjectif $o, int $index): void {
                $o->update(['ordre' => $index + 1]);
            }
        );

        return back()->with('success', 'Objectif supprimé.');
    }

    /**
     * Réordonne les objectifs d'un cours.
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

        foreach ($validated['ordre'] as $index => $objectifId) {
            CoursObjectif::where('id', $objectifId)
                ->where('cours_id', $cours->id)
                ->update(['ordre' => $index + 1]);
        }

        return back();
    }
}
