<?php

namespace App\Http\Controllers;

use App\Models\Cours;
use App\Models\TypeProjet;
use App\Models\TypeProjetTache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TypeProjetTacheController extends Controller
{
    /**
     * Ajoute une tâche dans un TypeProjet de type 'tache'.
     *
     * Accessible aux enseignants et admins uniquement (authorize sur le cours).
     */
    public function store(
        Request $request,
        Cours $cours,
        TypeProjet $typeProjet,
    ): RedirectResponse {
        $this->authorize('update', $cours);
        abort_if($typeProjet->cours_id !== $cours->id, 404);

        $data = $request->validate([
            'titre' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $ordre = ($typeProjet->taches()->max('ordre') ?? 0) + 1;

        $typeProjet->taches()->create([
            'titre' => $data['titre'],
            'description' => $data['description'] ?? null,
            'ordre' => $ordre,
        ]);

        return back()->with('success', __('taches.added'));
    }

    /**
     * Met à jour le titre ou la description d'une tâche.
     */
    public function update(
        Request $request,
        Cours $cours,
        TypeProjet $typeProjet,
        TypeProjetTache $tache,
    ): RedirectResponse {
        $this->authorize('update', $cours);
        abort_if($typeProjet->cours_id !== $cours->id, 404);
        abort_if($tache->type_projet_id !== $typeProjet->id, 404);

        $data = $request->validate([
            'titre' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $tache->update($data);

        return back()->with('success', __('taches.updated'));
    }

    /**
     * Réordonne les tâches d'un TypeProjet.
     *
     * Reçoit un tableau d'IDs dans l'ordre désiré.
     */
    public function reorder(
        Request $request,
        Cours $cours,
        TypeProjet $typeProjet,
    ): RedirectResponse {
        $this->authorize('update', $cours);
        abort_if($typeProjet->cours_id !== $cours->id, 404);

        $validated = $request->validate([
            'ordre' => ['required', 'array'],
            'ordre.*' => ['required', 'integer'],
        ]);

        foreach ($validated['ordre'] as $index => $tacheId) {
            TypeProjetTache::where('id', $tacheId)
                ->where('type_projet_id', $typeProjet->id)
                ->update(['ordre' => $index + 1]);
        }

        return back();
    }

    /**
     * Supprime une tâche et renumérote les tâches restantes.
     */
    public function destroy(
        Cours $cours,
        TypeProjet $typeProjet,
        TypeProjetTache $tache,
    ): RedirectResponse {
        $this->authorize('update', $cours);
        abort_if($typeProjet->cours_id !== $cours->id, 404);
        abort_if($tache->type_projet_id !== $typeProjet->id, 404);

        $tache->delete();

        // Renuméroter les tâches restantes
        $typeProjet->taches()->each(
            function (TypeProjetTache $t, int $index): void {
                $t->update(['ordre' => $index + 1]);
            }
        );

        return back()->with('success', __('taches.deleted'));
    }
}
