<?php

namespace App\Http\Controllers;

use App\Models\Cours;
use App\Models\CoursLienEntrevue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CoursLienEntrevueController extends Controller
{
    /**
     * Ajoute un lien d'entrevue au cours.
     *
     * Accessible aux enseignants et admins (authorize sur le cours).
     */
    public function store(Request $request, Cours $cours): RedirectResponse
    {
        $this->authorize('update', $cours);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:2048'],
        ]);

        $ordre = ($cours->liensEntrevue()->max('ordre') ?? 0) + 1;

        $cours->liensEntrevue()->create([
            'label' => $data['label'],
            'url' => $data['url'],
            'ordre' => $ordre,
        ]);

        return back()->with('success', __('liens_entrevue.added'));
    }

    /**
     * Met à jour un lien d'entrevue existant.
     */
    public function update(
        Request $request,
        Cours $cours,
        CoursLienEntrevue $lien,
    ): RedirectResponse {
        $this->authorize('update', $cours);
        abort_if($lien->cours_id !== $cours->id, 404);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:2048'],
        ]);

        $lien->update($data);

        return back()->with('success', __('liens_entrevue.updated'));
    }

    /**
     * Réordonne les liens d'entrevue du cours.
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

        foreach ($validated['ordre'] as $index => $lienId) {
            CoursLienEntrevue::where('id', $lienId)
                ->where('cours_id', $cours->id)
                ->update(['ordre' => $index + 1]);
        }

        return back();
    }

    /**
     * Supprime un lien d'entrevue et renumérote les restants.
     */
    public function destroy(Cours $cours, CoursLienEntrevue $lien): RedirectResponse
    {
        $this->authorize('update', $cours);
        abort_if($lien->cours_id !== $cours->id, 404);

        $lien->delete();

        $cours->liensEntrevue()->each(
            function (CoursLienEntrevue $l, int $index): void {
                $l->update(['ordre' => $index + 1]);
            }
        );

        return back()->with('success', __('liens_entrevue.deleted'));
    }
}
