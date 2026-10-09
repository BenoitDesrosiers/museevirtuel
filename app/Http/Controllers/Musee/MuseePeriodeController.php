<?php

namespace App\Http\Controllers\Musee;

use App\Http\Controllers\Controller;
use App\Models\Cours;
use App\Models\MuseePeriode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MuseePeriodeController extends Controller
{
    /**
     * Crée une nouvelle période pour le cours donné.
     */
    public function store(Request $request, Cours $cours): RedirectResponse
    {
        $this->authorize('update', $cours);

        $data = $request->validate([
            'nom' => ['required', 'string', 'max:150'],
        ]);

        $ordre = ($cours->museePeriodes()->max('ordre') ?? 0) + 1;

        $cours->museePeriodes()->create([
            'enseignant_id' => auth()->id(),
            'nom' => $data['nom'],
            'ordre' => $ordre,
        ]);

        return back()->with('success', 'Période ajoutée.');
    }

    /**
     * Met à jour le nom d'une période.
     */
    public function update(Request $request, Cours $cours, MuseePeriode $museePeriode): RedirectResponse
    {
        $this->authorize('update', $cours);
        abort_if($museePeriode->cours_id !== $cours->id, 404);

        $data = $request->validate([
            'nom' => ['required', 'string', 'max:150'],
        ]);

        $museePeriode->update($data);

        return back()->with('success', 'Période mise à jour.');
    }

    /**
     * Réordonne les périodes d'un cours.
     *
     * Reçoit un tableau d'IDs dans l'ordre désiré.
     */
    public function reorder(Request $request, Cours $cours): RedirectResponse
    {
        $this->authorize('update', $cours);

        $validated = $request->validate([
            'ordre' => ['required', 'array'],
            'ordre.*' => ['required', 'integer'],
        ]);

        foreach ($validated['ordre'] as $index => $periodeId) {
            MuseePeriode::where('id', $periodeId)
                ->where('cours_id', $cours->id)
                ->update(['ordre' => $index + 1]);
        }

        return back();
    }

    /**
     * Supprime une période.
     */
    public function destroy(Cours $cours, MuseePeriode $museePeriode): RedirectResponse
    {
        $this->authorize('update', $cours);
        abort_if($museePeriode->cours_id !== $cours->id, 404);

        $museePeriode->delete();

        // Renuméroter pour éviter les trous
        $cours->museePeriodes()->orderBy('ordre')->each(
            function (MuseePeriode $p, int $index): void {
                $p->update(['ordre' => $index + 1]);
            }
        );

        return back()->with('success', 'Période supprimée.');
    }
}
