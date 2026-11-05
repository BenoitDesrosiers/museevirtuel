<?php

namespace App\Http\Controllers\TypeProjet;

use App\Http\Controllers\Controller;
use App\Http\Requests\TypeProjet\ReorderCritereRequest;
use App\Http\Requests\TypeProjet\StoreCritereRequest;
use App\Http\Requests\TypeProjet\UpdateCritereRequest;
use App\Http\Requests\TypeProjet\UpdateVisibiliteGroupeCritereRequest;
use App\Models\Cours;
use App\Models\TypeProjet;
use App\Models\TypeProjetCritere;
use App\Models\TypeProjetSection;
use App\Services\OrdreElements;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;

class TypeProjetCritereController extends Controller
{
    /**
     * Ajoute un critère de correction à un TypeProjet.
     *
     * Si `section_id` est fourni, le critère est rattaché à cette section.
     * Sans `section_id`, le critère est global (affiché avant les sections).
     * L'appartenance de la section au TypeProjet est vérifiée avant l'insertion.
     */
    public function store(
        StoreCritereRequest $request,
        Cours $cours,
        TypeProjet $typeProjet,
        OrdreElements $ordres,
    ): RedirectResponse {
        $data = $request->validated();
        $sectionId = $data['section_id'] ?? null;

        // Vérifier que la section appartient bien à ce TypeProjet
        if ($sectionId !== null) {
            $section = TypeProjetSection::find($sectionId);
            abort_if($section === null || $section->type_projet_id !== $typeProjet->id, 404);
        }

        // Calculer le prochain ordre dans le même contexte (global ou par section)
        $ordre = $ordres->prochain($typeProjet->criteres()->where('section_id', $sectionId));

        $typeProjet->criteres()->create([
            'section_id' => $sectionId,
            'type' => $data['type'],
            'contenu_type' => $data['contenu_type'],
            'pointage' => $data['pointage'],
            'contenu' => $data['contenu'] ?? null,
            'note' => $data['note'] ?? null,
            'echelle' => $data['echelle'] ?? null,
            'visible' => $request->boolean('visible', true),
            'ordre' => $ordre,
        ]);

        return back()->with('success', __('critere.created'));
    }

    /**
     * Met à jour le contenu, le type et le pointage d'un critère.
     *
     * La section d'appartenance d'un critère ne peut pas être modifiée après création.
     */
    public function update(
        UpdateCritereRequest $request,
        Cours $cours,
        TypeProjet $typeProjet,
        TypeProjetCritere $critere,
    ): RedirectResponse {
        $data = $request->validated();

        $critere->update([
            'type' => $data['type'],
            'contenu_type' => $data['contenu_type'],
            'pointage' => $data['pointage'],
            'contenu' => $data['contenu'] ?? null,
            'note' => $data['note'] ?? null,
            'echelle' => $data['echelle'] ?? null,
            'visible' => $request->boolean('visible', $critere->visible),
        ]);

        return back()->with('success', __('critere.updated'));
    }

    /**
     * Réordonne les critères d'un TypeProjet.
     *
     * Reçoit un tableau d'IDs dans l'ordre désiré.
     * L'UI envoie uniquement les IDs d'un même groupe (global ou section donnée).
     */
    public function reorder(
        ReorderCritereRequest $request,
        Cours $cours,
        TypeProjet $typeProjet,
        OrdreElements $ordres,
    ): RedirectResponse {
        $ordres->reordonner($typeProjet->criteres(), $request->ids());

        return back();
    }

    /**
     * Rend tous les critères d'un type (positif ou négatif) visibles pour les étudiants.
     *
     * Permet au professeur de basculer la visibilité de l'ensemble des critères
     * d'un type en une seule action depuis la page d'édition du TypeProjet.
     * Les données sont validées par UpdateVisibiliteGroupeCritereRequest.
     */
    public function toggleVisibiliteGroupe(
        UpdateVisibiliteGroupeCritereRequest $request,
        Cours $cours,
        TypeProjet $typeProjet,
    ): RedirectResponse {
        $validated = $request->validated();

        $typeProjet->criteres()
            ->where('type', $validated['type'])
            ->update(['visible' => $validated['visible']]);

        return back();
    }

    /**
     * Supprime un critère et renumérote les critères restants du même groupe.
     *
     * La renumération cible uniquement les critères partageant le même
     * (type_projet_id, section_id) que le critère supprimé.
     *
     * @throws AuthorizationException
     */
    public function destroy(
        Cours $cours,
        TypeProjet $typeProjet,
        TypeProjetCritere $critere,
        OrdreElements $ordres,
    ): RedirectResponse {
        abort_if($typeProjet->cours_id !== $cours->id, 404);
        $this->authorize('update', $typeProjet);
        abort_if($critere->type_projet_id !== $typeProjet->id, 404);

        // Renuméroter seulement les critères du même groupe (global ou même section)
        $ordres->supprimer($critere, $typeProjet->criteres()->where('section_id', $critere->section_id));

        return back()->with('success', __('critere.deleted'));
    }
}
