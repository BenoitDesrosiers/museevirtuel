<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCritereRequest;
use App\Http\Requests\UpdateCritereRequest;
use App\Models\Cours;
use App\Models\TypeProjet;
use App\Models\TypeProjetCritere;
use App\Models\TypeProjetSection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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
    ): RedirectResponse {
        $this->authorize('update', $cours);
        abort_if($typeProjet->cours_id !== $cours->id, 404);

        $data = $request->validated();
        $sectionId = $data['section_id'] ?? null;

        // Vérifier que la section appartient bien à ce TypeProjet
        if ($sectionId !== null) {
            $section = TypeProjetSection::find($sectionId);
            abort_if($section === null || $section->type_projet_id !== $typeProjet->id, 404);
        }

        // Calculer le prochain ordre dans le même contexte (global ou par section)
        $ordre = ($typeProjet->criteres()
            ->where('section_id', $sectionId)
            ->max('ordre') ?? 0) + 1;

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

        return back()->with('success', 'Critère ajouté.');
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
        $this->authorize('update', $cours);
        abort_if($typeProjet->cours_id !== $cours->id, 404);
        abort_if($critere->type_projet_id !== $typeProjet->id, 404);

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

        return back()->with('success', 'Critère mis à jour.');
    }

    /**
     * Réordonne les critères d'un TypeProjet.
     *
     * Reçoit un tableau d'IDs dans l'ordre désiré.
     * L'UI envoie uniquement les IDs d'un même groupe (global ou section donnée).
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

        foreach ($validated['ordre'] as $index => $critereId) {
            TypeProjetCritere::where('id', $critereId)
                ->where('type_projet_id', $typeProjet->id)
                ->update(['ordre' => $index + 1]);
        }

        return back();
    }

    /**
     * Rend tous les critères d'un type (positif ou négatif) visibles pour les étudiants.
     *
     * Permet au professeur de basculer la visibilité de l'ensemble des critères
     * d'un type en une seule action depuis la page d'édition du TypeProjet.
     */
    public function toggleVisibleGroupe(
        Request $request,
        Cours $cours,
        TypeProjet $typeProjet,
    ): RedirectResponse {
        $this->authorize('update', $cours);
        abort_if($typeProjet->cours_id !== $cours->id, 404);

        $validated = $request->validate([
            'type' => ['required', 'in:positif,negatif'],
            'visible' => ['required', 'boolean'],
        ]);

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
     */
    public function destroy(
        Cours $cours,
        TypeProjet $typeProjet,
        TypeProjetCritere $critere,
    ): RedirectResponse {
        $this->authorize('update', $cours);
        abort_if($typeProjet->cours_id !== $cours->id, 404);
        abort_if($critere->type_projet_id !== $typeProjet->id, 404);

        // Sauvegarder section_id avant suppression pour la renumération
        $sectionId = $critere->section_id;

        $critere->delete();

        // Renuméroter les critères restants dans le même groupe
        TypeProjetCritere::where('type_projet_id', $typeProjet->id)
            ->where('section_id', $sectionId)
            ->orderBy('ordre')
            ->each(function (TypeProjetCritere $c, int $index): void {
                $c->update(['ordre' => $index + 1]);
            });

        return back()->with('success', 'Critère supprimé.');
    }
}
