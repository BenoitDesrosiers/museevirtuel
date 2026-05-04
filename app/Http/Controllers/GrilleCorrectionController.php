<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGrilleCorrectionRequest;
use App\Http\Requests\UpdateGrilleCorrectionRequest;
use App\Models\Cours;
use App\Models\GrilleCritere;
use App\Models\GrilleMalus;
use App\Models\TypeProjet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class GrilleCorrectionController extends Controller
{
    /**
     * Affiche la page de gestion de la grille de correction d'un type de projet.
     *
     * Sert à la fois de page de création et d'édition selon si la grille existe déjà.
     */
    public function edit(Cours $cours, TypeProjet $typeProjet): Response
    {
        $this->authorize('update', $cours);
        abort_if($typeProjet->cours_id !== $cours->id, 404);

        $grille = $typeProjet->grille?->load(['criteres', 'malus']);

        return Inertia::render('GrilleCorrection/Edit', [
            'cours' => $cours,
            'typeProjet' => $typeProjet->only(['id', 'nom', 'description']),
            'grille' => $grille,
        ]);
    }

    /**
     * Enregistre une nouvelle grille de correction pour un type de projet.
     *
     * Le type de projet ne doit pas encore avoir de grille (contrainte unique DB + authorize).
     */
    public function store(StoreGrilleCorrectionRequest $request, Cours $cours, TypeProjet $typeProjet): RedirectResponse
    {
        abort_if($typeProjet->cours_id !== $cours->id, 404);

        $data = $request->validated();

        DB::transaction(function () use ($data, $typeProjet): void {
            $grille = $typeProjet->grille()->create([
                'nom' => $data['nom'],
                'description' => $data['description'] ?? null,
            ]);

            $criteres = collect($data['criteres'])->values()->map(fn (array $c, int $i): array => [
                'label' => $c['label'],
                'ponderation' => $c['ponderation'],
                'ordre' => $i,
            ]);

            $grille->criteres()->createMany($criteres->all());

            if (! empty($data['malus'])) {
                $malus = collect($data['malus'])->values()->map(fn (array $m, int $i): array => [
                    'label' => $m['label'],
                    'deduction' => $m['deduction'],
                    'description' => $m['description'] ?? null,
                    'ordre' => $i,
                ]);

                $grille->malus()->createMany($malus->all());
            }
        });

        return redirect()->route('types-projets.edit', [$cours, $typeProjet])
            ->with('success', 'Grille de correction créée.');
    }

    /**
     * Met à jour la grille de correction existante d'un type de projet.
     *
     * Stratégie de synchronisation des critères et malus :
     * - Ligne avec `id` → mise à jour du label/pondération/déduction
     * - Ligne sans `id`  → création
     * - Ligne absente    → suppression (cascade sur projet_grille_notes et projet_grille_malus)
     */
    public function update(UpdateGrilleCorrectionRequest $request, Cours $cours, TypeProjet $typeProjet): RedirectResponse
    {
        abort_if($typeProjet->cours_id !== $cours->id, 404);

        $grille = $typeProjet->grille;
        $data = $request->validated();

        DB::transaction(function () use ($data, $grille): void {
            $grille->update([
                'nom' => $data['nom'],
                'description' => $data['description'] ?? null,
            ]);

            // Synchronisation des critères
            $criteresPayload = collect($data['criteres'])->values();
            $criteresIds = $criteresPayload->pluck('id')->filter()->values();

            $grille->criteres()->whereNotIn('id', $criteresIds)->delete();

            foreach ($criteresPayload as $i => $c) {
                if (! empty($c['id'])) {
                    GrilleCritere::where('id', $c['id'])
                        ->where('grille_id', $grille->id)
                        ->update([
                            'label' => $c['label'],
                            'ponderation' => $c['ponderation'],
                            'ordre' => $i,
                        ]);
                } else {
                    $grille->criteres()->create([
                        'label' => $c['label'],
                        'ponderation' => $c['ponderation'],
                        'ordre' => $i,
                    ]);
                }
            }

            // Synchronisation des malus
            $malusPayload = collect($data['malus'] ?? [])->values();
            $malusIds = $malusPayload->pluck('id')->filter()->values();

            $grille->malus()->whereNotIn('id', $malusIds)->delete();

            foreach ($malusPayload as $i => $m) {
                if (! empty($m['id'])) {
                    GrilleMalus::where('id', $m['id'])
                        ->where('grille_id', $grille->id)
                        ->update([
                            'label' => $m['label'],
                            'deduction' => $m['deduction'],
                            'description' => $m['description'] ?? null,
                            'ordre' => $i,
                        ]);
                } else {
                    $grille->malus()->create([
                        'label' => $m['label'],
                        'deduction' => $m['deduction'],
                        'description' => $m['description'] ?? null,
                        'ordre' => $i,
                    ]);
                }
            }
        });

        return redirect()->route('types-projets.edit', [$cours, $typeProjet])
            ->with('success', 'Grille de correction mise à jour.');
    }

    /**
     * Supprime la grille de correction d'un type de projet.
     *
     * La suppression est en cascade sur les critères, malus, et toutes les notes associées.
     */
    public function destroy(Cours $cours, TypeProjet $typeProjet): RedirectResponse
    {
        $this->authorize('update', $cours);
        abort_if($typeProjet->cours_id !== $cours->id, 404);

        $typeProjet->grille?->delete();

        return redirect()->route('types-projets.edit', [$cours, $typeProjet])
            ->with('success', 'Grille de correction supprimée.');
    }
}
