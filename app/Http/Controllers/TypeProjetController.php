<?php

namespace App\Http\Controllers;

use App\Models\TypeProjet;
use App\Models\TypeProjetSection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TypeProjetController extends Controller
{
    /**
     * Affiche la liste des types de projet de l'enseignant connecté.
     */
    public function index(): Response
    {
        $typesProjets = TypeProjet::where('enseignant_id', auth()->id())
            ->with(['grille:id,type_projet_id,nom', 'sections'])
            ->orderBy('nom')
            ->get();

        return Inertia::render('TypeProjet/Index', [
            'typesProjets' => $typesProjets,
        ]);
    }

    /**
     * Affiche la page de création d'un type de projet.
     */
    public function create(): Response
    {
        return Inertia::render('TypeProjet/Create');
    }

    /**
     * Affiche la page d'édition dédiée d'un type de projet.
     */
    public function edit(TypeProjet $typeProjet): Response
    {
        $this->authorize('update', $typeProjet);

        $typeProjet->load(['sections' => fn ($q) => $q->orderBy('ordre')]);

        return Inertia::render('TypeProjet/Edit', [
            'typeProjet' => $typeProjet,
        ]);
    }

    /**
     * Crée un nouveau type de projet pour l'enseignant connecté.
     *
     * Accepte un tableau optionnel `sections[]` pour créer les sections en une seule requête.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'date_remise' => ['nullable', 'date'],
            'remises_multiples' => ['boolean'],
            'retard_permis' => ['boolean'],
            'generer_page_titre' => ['boolean'],
            'generer_table_matieres' => ['boolean'],
            'sections' => ['nullable', 'array'],
            'sections.*.label' => ['required', 'string', 'max:200'],
            'sections.*.description' => ['nullable', 'string', 'max:1000'],
            'sections.*.type' => ['nullable', 'string', 'in:texte,paragraphes,individuel,entrevue'],
        ]);

        $typeProjet = TypeProjet::create([
            'enseignant_id' => auth()->id(),
            'nom' => $data['nom'],
            'description' => $data['description'] ?? null,
            'accessible' => false,
            'date_remise' => $data['date_remise'] ?? null,
            'remises_multiples' => $data['remises_multiples'] ?? false,
            'retard_permis' => $data['retard_permis'] ?? false,
            'generer_page_titre' => $data['generer_page_titre'] ?? true,
            'generer_table_matieres' => $data['generer_table_matieres'] ?? true,
        ]);

        foreach ($data['sections'] ?? [] as $index => $section) {
            $typeProjet->sections()->create([
                'label' => $section['label'],
                'description' => $section['description'] ?? null,
                'type' => $section['type'] ?? 'texte',
                'ordre' => $index + 1,
            ]);
        }

        return redirect()->route('types-projets.edit', $typeProjet)
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
    public function update(Request $request, TypeProjet $typeProjet): RedirectResponse
    {
        $this->authorize('update', $typeProjet);

        $data = $request->validate([
            'nom' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'date_remise' => ['nullable', 'date'],
            'remises_multiples' => ['boolean'],
            'retard_permis' => ['boolean'],
            'generer_page_titre' => ['boolean'],
            'generer_table_matieres' => ['boolean'],
            'sections' => ['nullable', 'array'],
            'sections.*.id' => ['nullable', 'integer'],
            'sections.*.label' => ['required', 'string', 'max:200'],
            'sections.*.description' => ['nullable', 'string', 'max:1000'],
            'sections.*.type' => ['nullable', 'string', 'in:texte,paragraphes,individuel,entrevue'],
        ]);

        $typeProjet->update([
            'nom' => $data['nom'],
            'description' => $data['description'] ?? null,
            'date_remise' => $data['date_remise'] ?? null,
            'remises_multiples' => $data['remises_multiples'] ?? false,
            'retard_permis' => $data['retard_permis'] ?? false,
            'generer_page_titre' => $data['generer_page_titre'] ?? $typeProjet->generer_page_titre,
            'generer_table_matieres' => $data['generer_table_matieres'] ?? $typeProjet->generer_table_matieres,
        ]);

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
                            'type' => $sec['type'] ?? 'texte',
                            'ordre' => $index + 1,
                        ]);
                } else {
                    $typeProjet->sections()->create([
                        'label' => $sec['label'],
                        'description' => $sec['description'] ?? null,
                        'type' => $sec['type'] ?? 'texte',
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
    public function toggleAccessible(TypeProjet $typeProjet): RedirectResponse
    {
        $this->authorize('update', $typeProjet);

        $typeProjet->update(['accessible' => ! $typeProjet->accessible]);

        return back();
    }

    /**
     * Supprime un type de projet ainsi que sa grille en cascade.
     */
    public function destroy(TypeProjet $typeProjet): RedirectResponse
    {
        $this->authorize('delete', $typeProjet);

        $typeProjet->delete();

        return back()->with('success', 'Type de projet supprimé.');
    }

    /**
     * Ajoute une section au type de projet.
     */
    public function storeSection(Request $request, TypeProjet $typeProjet): RedirectResponse
    {
        $this->authorize('update', $typeProjet);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['nullable', 'string', 'in:texte,paragraphes,individuel'],
        ]);

        $ordre = ($typeProjet->sections()->max('ordre') ?? 0) + 1;

        $typeProjet->sections()->create([
            'label' => $data['label'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'] ?? 'texte',
            'ordre' => $ordre,
        ]);

        return back()->with('success', 'Section ajoutée.');
    }

    /**
     * Met à jour le label et la description d'une section.
     */
    public function updateSection(Request $request, TypeProjet $typeProjet, TypeProjetSection $section): RedirectResponse
    {
        $this->authorize('update', $typeProjet);
        abort_if($section->type_projet_id !== $typeProjet->id, 404);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['nullable', 'string', 'in:texte,paragraphes,individuel'],
        ]);

        $section->update($data);

        return back()->with('success', 'Section mise à jour.');
    }

    /**
     * Réordonne les sections d'un type de projet.
     *
     * Reçoit un tableau d'IDs dans l'ordre désiré.
     */
    public function reorderSections(Request $request, TypeProjet $typeProjet): RedirectResponse
    {
        $this->authorize('update', $typeProjet);

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
    public function destroySection(TypeProjet $typeProjet, TypeProjetSection $section): RedirectResponse
    {
        $this->authorize('update', $typeProjet);
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
