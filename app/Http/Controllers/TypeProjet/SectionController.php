<?php

namespace App\Http\Controllers\TypeProjet;

use App\Enums\TypeSection;
use App\Http\Controllers\Controller;
use App\Models\Cours;
use App\Models\TypeProjet;
use App\Models\TypeProjetSection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SectionController extends Controller
{
    /**
     * Ajoute une section au type de projet.
     */
    public function store(Request $request, Cours $cours, TypeProjet $typeProjet): RedirectResponse
    {
        $this->authorize('update', $cours);
        abort_if($typeProjet->cours_id !== $cours->id, 404);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['nullable', Rule::enum(TypeSection::class)],
        ]);

        $ordre = ($typeProjet->sections()->max('ordre') ?? 0) + 1;

        $typeProjet->sections()->create([
            'label' => $data['label'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'] ?? TypeSection::Texte->value,
            'ordre' => $ordre,
        ]);

        return back()->with('success', 'Section ajoutée.');
    }

    /**
     * Met à jour le label et la description d'une section.
     */
    public function update(Request $request, Cours $cours, TypeProjet $typeProjet, TypeProjetSection $section): RedirectResponse
    {
        $this->authorize('update', $cours);
        abort_if($typeProjet->cours_id !== $cours->id, 404);
        abort_if($section->type_projet_id !== $typeProjet->id, 404);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['nullable', Rule::enum(TypeSection::class)],
        ]);

        $section->update($data);

        return back()->with('success', 'Section mise à jour.');
    }

    /**
     * Réordonne les sections d'un type de projet.
     *
     * Reçoit un tableau d'IDs dans l'ordre désiré.
     */
    public function reorder(Request $request, Cours $cours, TypeProjet $typeProjet): RedirectResponse
    {
        $this->authorize('update', $cours);
        abort_if($typeProjet->cours_id !== $cours->id, 404);

        $validated = $request->validate([
            'ordre' => ['required', 'array'],
            'ordre.*' => ['required', 'integer'],
        ]);

        foreach ($validated['ordre'] as $index => $sectionId) {
            TypeProjetSection::where('id', $sectionId)
                ->where('type_projet_id', $typeProjet->id)
                ->update(['ordre' => $index + 1]);
        }

        return back();
    }

    /**
     * Supprime une section du type de projet.
     */
    public function destroy(Cours $cours, TypeProjet $typeProjet, TypeProjetSection $section): RedirectResponse
    {
        $this->authorize('update', $cours);
        abort_if($typeProjet->cours_id !== $cours->id, 404);
        abort_if($section->type_projet_id !== $typeProjet->id, 404);

        $section->delete();

        $typeProjet->sections()->orderBy('ordre')->each(
            function (TypeProjetSection $section, int $index): void {
                $section->update(['ordre' => $index + 1]);
            }
        );

        return back()->with('success', 'Section supprimée.');
    }
}
