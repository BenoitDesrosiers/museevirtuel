<?php

namespace App\Http\Controllers\TypeProjet;

use App\Enums\TypeSection;
use App\Exceptions\TransactionEchoueeException;
use App\Http\Controllers\Controller;
use App\Http\Requests\TypeProjet\ReorderTypeProjetSectionRequest;
use App\Http\Requests\TypeProjet\SaveTypeProjetSectionRequest;
use App\Models\Cours;
use App\Models\TypeProjet;
use App\Models\TypeProjetSection;
use App\Services\OrdreElements;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;

class TypeProjetSectionController extends Controller
{
    /**
     * Ajoute une section au type de projet.
     *
     * Les données sont validées par SaveTypeProjetSectionRequest.
     */
    public function store(SaveTypeProjetSectionRequest $request, Cours $cours, TypeProjet $typeProjet, OrdreElements $ordres): RedirectResponse
    {
        $data = $request->validated();

        $ordre = $ordres->prochain($typeProjet->sections());

        $typeProjet->sections()->create([
            'label' => $data['label'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'] ?? TypeSection::Texte->value,
            'ordre' => $ordre,
        ]);

        return back()->with('success', __('type_projet_section.created'));
    }

    /**
     * Met à jour le label et la description d'une section.
     *
     * Les données sont validées par SaveTypeProjetSectionRequest.
     */
    public function update(SaveTypeProjetSectionRequest $request, Cours $cours, TypeProjet $typeProjet, TypeProjetSection $section): RedirectResponse
    {
        $data = $request->validated();

        $section->update($data);

        return back()->with('success', __('type_projet_section.updated'));
    }

    /**
     * Réordonne les sections d'un type de projet.
     *
     * Reçoit un tableau d'IDs dans l'ordre désiré.
     */
    public function reorder(ReorderTypeProjetSectionRequest $request, Cours $cours, TypeProjet $typeProjet, OrdreElements $ordres): RedirectResponse
    {
        try {
            $ordres->reordonner($typeProjet->sections(), $request->ids());
        } catch (TransactionEchoueeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back();
    }

    /**
     * Supprime une section du type de projet.
     *
     * @throws AuthorizationException
     */
    public function destroy(Cours $cours, TypeProjet $typeProjet, TypeProjetSection $section, OrdreElements $ordres): RedirectResponse
    {
        abort_if($typeProjet->cours_id !== $cours->id, 404);
        $this->authorize('update', $typeProjet);
        abort_if($section->type_projet_id !== $typeProjet->id, 404);

        try {
            $ordres->supprimer($section, $typeProjet->sections());
        } catch (TransactionEchoueeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('type_projet_section.deleted'));
    }
}
