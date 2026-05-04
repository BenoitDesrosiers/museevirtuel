<?php

namespace App\Http\Controllers;

use App\Enums\TypeSection;
use App\Models\Cours;
use App\Models\TypeProjet;
use App\Models\TypeProjetSection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TypeProjetController extends Controller
{
    /**
     * Affiche la liste des types de projet d'un cours.
     */
    public function index(Cours $cours): Response
    {
        $this->authorize('update', $cours);

        $typesProjets = TypeProjet::where('cours_id', $cours->id)
            ->with(['grille:id,type_projet_id,nom', 'sections'])
            ->orderBy('nom')
            ->get();

        return Inertia::render('TypeProjet/Index', [
            'cours' => $cours,
            'typesProjets' => $typesProjets,
        ]);
    }

    /**
     * Affiche la page de création d'un type de projet pour un cours donné.
     */
    public function create(Cours $cours): Response
    {
        $this->authorize('update', $cours);

        return Inertia::render('TypeProjet/Create', [
            'cours' => $cours,
        ]);
    }

    /**
     * Affiche la page d'édition dédiée d'un type de projet.
     */
    public function edit(Cours $cours, TypeProjet $typeProjet): Response
    {
        $this->authorize('update', $cours);
        abort_if($typeProjet->cours_id !== $cours->id, 404);

        $typeProjet->load(['sections' => fn ($q) => $q->with('questionsBanque')->orderBy('ordre')]);

        return Inertia::render('TypeProjet/Edit', [
            'cours' => $cours,
            'typeProjet' => $typeProjet,
        ]);
    }

    /**
     * Crée un nouveau type de projet pour le cours donné.
     *
     * Accepte un tableau optionnel `sections[]` pour créer les sections en une seule requête.
     */
    public function store(Request $request, Cours $cours): RedirectResponse
    {
        $this->authorize('update', $cours);

        $data = $request->validate([
            'nom' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'date_remise' => ['nullable', 'date'],
            'remises_multiples' => ['boolean'],
            'retard_permis' => ['boolean'],
            'generer_page_titre' => ['boolean'],
            'generer_table_matieres' => ['boolean'],
            'ponderation' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_sommatif' => ['boolean'],
            'sections' => ['nullable', 'array'],
            'sections.*.label' => ['required', 'string', 'max:200'],
            'sections.*.description' => ['nullable', 'string', 'max:1000'],
            'sections.*.type' => ['nullable', Rule::enum(TypeSection::class)],
        ]);

        $typeProjet = TypeProjet::create([
            'enseignant_id' => auth()->id(),
            'cours_id' => $cours->id,
            'nom' => $data['nom'],
            'description' => $data['description'] ?? null,
            'accessible' => false,
            'date_remise' => $data['date_remise'] ?? null,
            'remises_multiples' => $data['remises_multiples'] ?? false,
            'retard_permis' => $data['retard_permis'] ?? false,
            'generer_page_titre' => $request->boolean('generer_page_titre', true),
            'generer_table_matieres' => $request->boolean('generer_table_matieres', true),
            'ponderation' => $data['ponderation'] ?? null,
            'is_sommatif' => $request->boolean('is_sommatif', true),
        ]);

        foreach ($data['sections'] ?? [] as $index => $section) {
            $typeProjet->sections()->create([
                'label' => $section['label'],
                'description' => $section['description'] ?? null,
                'type' => $section['type'] ?? TypeSection::Texte->value,
                'ordre' => $index + 1,
            ]);
        }

        return redirect()->route('types-projets.edit', [$cours, $typeProjet])
            ->with('success', 'Type de projet créé.');
    }

    /**
     * Met à jour le nom, la description et synchronise les sections d'un type de projet.
     *
     * Quand `sections[]` est présent, effectue un sync complet :
     * les sections absentes sont supprimées (cascade sur projet_section_contenus),
     * les existantes (avec `id`) sont mises à jour, les nouvelles (sans `id`) sont créées.
     * Si `sections` est absent de la requête, les sections ne sont pas touchées.
     */
    public function update(Request $request, Cours $cours, TypeProjet $typeProjet): RedirectResponse
    {
        $this->authorize('update', $cours);
        abort_if($typeProjet->cours_id !== $cours->id, 404);

        $data = $request->validate([
            'nom' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'date_remise' => ['nullable', 'date'],
            'remises_multiples' => ['boolean'],
            'retard_permis' => ['boolean'],
            'generer_page_titre' => ['boolean'],
            'generer_table_matieres' => ['boolean'],
            'ponderation' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_sommatif' => ['boolean'],
            'sections' => ['nullable', 'array'],
            'sections.*.id' => ['nullable', 'integer'],
            'sections.*.label' => ['required', 'string', 'max:200'],
            'sections.*.description' => ['nullable', 'string', 'max:1000'],
            'sections.*.type' => ['nullable', Rule::enum(TypeSection::class)],
        ]);

        $wasGeneratingPageTitre = (bool) $typeProjet->generer_page_titre;
        $willGeneratePageTitre = $request->boolean('generer_page_titre', $typeProjet->generer_page_titre);

        $typeProjet->update([
            'nom' => $data['nom'],
            'description' => $data['description'] ?? null,
            'date_remise' => $data['date_remise'] ?? null,
            'remises_multiples' => $request->boolean('remises_multiples', $typeProjet->remises_multiples),
            'retard_permis' => $request->boolean('retard_permis', $typeProjet->retard_permis),
            'generer_page_titre' => $willGeneratePageTitre,
            'generer_table_matieres' => $request->boolean('generer_table_matieres', $typeProjet->generer_table_matieres),
            'ponderation' => $data['ponderation'] ?? $typeProjet->ponderation,
            'is_sommatif' => $request->boolean('is_sommatif', $typeProjet->is_sommatif),
        ]);

        // Vider le contenu auto-généré quand le flag passe de auto → manuel
        if ($wasGeneratingPageTitre && ! $willGeneratePageTitre) {
            $typeProjet->projets()->update(['page_titre_contenu' => null]);
        }

        if ($request->has('sections')) {
            $sections = $data['sections'] ?? [];
            $incomingIds = collect($sections)->pluck('id')->filter()->values()->all();

            // Supprimer les sections retirées — la contrainte DB cascade sur projet_section_contenus
            if (! empty($incomingIds)) {
                $typeProjet->sections()->whereNotIn('id', $incomingIds)->delete();
            } else {
                $typeProjet->sections()->delete();
            }

            foreach ($sections as $index => $sec) {
                if (! empty($sec['id'])) {
                    TypeProjetSection::where('id', $sec['id'])
                        ->where('type_projet_id', $typeProjet->id)
                        ->update([
                            'label' => $sec['label'],
                            'description' => $sec['description'] ?? null,
                            'type' => $sec['type'] ?? TypeSection::Texte->value,
                            'ordre' => $index + 1,
                        ]);
                } else {
                    $typeProjet->sections()->create([
                        'label' => $sec['label'],
                        'description' => $sec['description'] ?? null,
                        'type' => $sec['type'] ?? TypeSection::Texte->value,
                        'ordre' => $index + 1,
                    ]);
                }
            }
        }

        return back()->with('success', 'Type de projet mis à jour.');
    }

    /**
     * Inverse le statut d'accessibilité du type de projet.
     *
     * Si `accessible = false`, les étudiants ne voient pas les projets associés.
     */
    public function toggleAccessible(Cours $cours, TypeProjet $typeProjet): RedirectResponse
    {
        $this->authorize('update', $cours);
        abort_if($typeProjet->cours_id !== $cours->id, 404);

        $typeProjet->update(['accessible' => ! $typeProjet->accessible]);

        return back();
    }

    /**
     * Supprime un type de projet ainsi que sa grille en cascade.
     */
    public function destroy(Cours $cours, TypeProjet $typeProjet): RedirectResponse
    {
        $this->authorize('update', $cours);
        abort_if($typeProjet->cours_id !== $cours->id, 404);

        $typeProjet->delete();

        return redirect()->route('types-projets.index', $cours)
            ->with('success', 'Type de projet supprimé.');
    }

    /**
     * Ajoute une section au type de projet.
     */
    public function storeSection(Request $request, Cours $cours, TypeProjet $typeProjet): RedirectResponse
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
    public function updateSection(Request $request, Cours $cours, TypeProjet $typeProjet, TypeProjetSection $section): RedirectResponse
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
    public function reorderSections(Request $request, Cours $cours, TypeProjet $typeProjet): RedirectResponse
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
    public function destroySection(Cours $cours, TypeProjet $typeProjet, TypeProjetSection $section): RedirectResponse
    {
        $this->authorize('update', $cours);
        abort_if($typeProjet->cours_id !== $cours->id, 404);
        abort_if($section->type_projet_id !== $typeProjet->id, 404);

        $section->delete();

        // Renuméroter les sections restantes pour éviter les trous
        $typeProjet->sections()->orderBy('ordre')->each(
            function (TypeProjetSection $s, int $index): void {
                $s->update(['ordre' => $index + 1]);
            }
        );

        return back()->with('success', 'Section supprimée.');
    }
}
