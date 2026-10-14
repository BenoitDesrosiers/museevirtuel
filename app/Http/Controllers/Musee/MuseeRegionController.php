<?php

namespace App\Http\Controllers\Musee;

use App\Http\Controllers\Controller;
use App\Models\Cours;
use App\Models\MuseeRegion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MuseeRegionController extends Controller
{
    /**
     * Crée une nouvelle région pour le cours donné.
     */
    public function store(Request $request, Cours $cours): RedirectResponse
    {
        $this->authorize('update', $cours);

        $data = $request->validate([
            'nom' => ['required', 'string', 'max:150'],
        ]);

        $ordre = ($cours->museeRegions()->max('ordre') ?? 0) + 1;

        $cours->museeRegions()->create([
            'enseignant_id' => auth()->id(),
            'nom' => $data['nom'],
            'ordre' => $ordre,
        ]);

        return back()->with('success', 'Région ajoutée.');
    }

    /**
     * Met à jour le nom d'une région.
     */
    public function update(Request $request, Cours $cours, MuseeRegion $museeRegion): RedirectResponse
    {
        $this->authorize('update', $cours);
        abort_if($museeRegion->cours_id !== $cours->id, 404);

        $data = $request->validate([
            'nom' => ['required', 'string', 'max:150'],
        ]);

        $museeRegion->update($data);

        return back()->with('success', 'Région mise à jour.');
    }

    /**
     * Réordonne les régions d'un cours.
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

        foreach ($validated['ordre'] as $index => $regionId) {
            MuseeRegion::where('id', $regionId)
                ->where('cours_id', $cours->id)
                ->update(['ordre' => $index + 1]);
        }

        return back();
    }

    /**
     * Supprime une région.
     */
    public function destroy(Cours $cours, MuseeRegion $museeRegion): RedirectResponse
    {
        $this->authorize('update', $cours);
        abort_if($museeRegion->cours_id !== $cours->id, 404);

        $museeRegion->delete();

        // Renuméroter pour éviter les trous
        $cours->museeRegions()->orderBy('ordre')->each(
            function (MuseeRegion $r, int $index): void {
                $r->update(['ordre' => $index + 1]);
            }
        );

        return back()->with('success', 'Région supprimée.');
    }
}
