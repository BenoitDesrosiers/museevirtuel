<?php

namespace App\Http\Controllers;

use App\Models\Classe;
use App\Models\EntrevueConcept;
use App\Models\EntrevueLigne;
use App\Models\Groupe;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use App\Models\TypeProjetSection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EntrevueConceptController extends Controller
{
    /**
     * Crée un nouveau concept dans une section de type 'entrevue'.
     *
     * @throws HttpException
     */
    public function store(Request $request, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, TypeProjetSection $section): JsonResponse
    {
        $this->autoriserEtVerifier($classe, $groupe, $typeProjet, $section);

        $projet = ProjetRecherche::firstOrCreate([
            'groupe_id' => $groupe->id,
            'type_projet_id' => $typeProjet->id,
        ]);

        abort_if($projet->verrouille, 403, 'Ce document est verrouillé.');
        abort_if(! $projet->peutEtreRemis(), 422, 'Ce travail a déjà été remis.');

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:500'],
        ]);

        $ordre = (EntrevueConcept::where('projet_id', $projet->id)
            ->where('section_id', $section->id)
            ->max('ordre') ?? 0) + 1;

        $concept = EntrevueConcept::create([
            'projet_id' => $projet->id,
            'section_id' => $section->id,
            'label' => $validated['label'],
            'ordre' => $ordre,
        ]);

        return response()->json([
            'message' => 'created',
            'concept' => $this->formaterConcept($concept),
        ], 201);
    }

    /**
     * Met à jour le label d'un concept existant.
     *
     * @throws HttpException
     */
    public function update(Request $request, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, TypeProjetSection $section, EntrevueConcept $concept): JsonResponse
    {
        $this->autoriserEtVerifier($classe, $groupe, $typeProjet, $section);

        $projet = $this->trouverProjet($groupe, $typeProjet);

        abort_if($concept->projet_id !== $projet->id || $concept->section_id !== $section->id, 404);
        abort_if($projet->verrouille, 403, 'Ce document est verrouillé.');
        abort_if(! $projet->peutEtreRemis(), 422, 'Ce travail a déjà été remis.');

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:500'],
        ]);

        $concept->update($validated);

        return response()->json(['message' => 'saved']);
    }

    /**
     * Supprime un concept et réordonne les suivants.
     *
     * @throws HttpException
     */
    public function destroy(Classe $classe, Groupe $groupe, TypeProjet $typeProjet, TypeProjetSection $section, EntrevueConcept $concept): JsonResponse
    {
        $this->autoriserEtVerifier($classe, $groupe, $typeProjet, $section);

        $projet = $this->trouverProjet($groupe, $typeProjet);

        abort_if($concept->projet_id !== $projet->id || $concept->section_id !== $section->id, 404);
        abort_if($projet->verrouille, 403, 'Ce document est verrouillé.');
        abort_if(! $projet->peutEtreRemis(), 422, 'Ce travail a déjà été remis.');

        $concept->delete();

        EntrevueConcept::where('projet_id', $projet->id)
            ->where('section_id', $section->id)
            ->orderBy('ordre')
            ->each(function (EntrevueConcept $c, int $index): void {
                $c->update(['ordre' => $index + 1]);
            });

        return response()->json(['message' => 'deleted']);
    }

    /**
     * Met à jour l'ordre de tous les concepts d'une section de type 'entrevue'.
     *
     * @throws HttpException
     */
    public function reorder(Request $request, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, TypeProjetSection $section): JsonResponse
    {
        $this->autoriserEtVerifier($classe, $groupe, $typeProjet, $section);

        $projet = $this->trouverProjet($groupe, $typeProjet);

        abort_if($projet->verrouille, 403, 'Ce document est verrouillé.');

        $validated = $request->validate([
            'ordre' => ['required', 'array'],
            'ordre.*' => ['required', 'integer', 'exists:entrevue_concepts,id'],
        ]);

        foreach ($validated['ordre'] as $index => $id) {
            EntrevueConcept::where('id', $id)
                ->where('projet_id', $projet->id)
                ->where('section_id', $section->id)
                ->update(['ordre' => $index + 1]);
        }

        return response()->json(['message' => 'reordered']);
    }

    /**
     * Ajoute une nouvelle ligne (dimension/indicateur/questions) à un concept.
     *
     * @throws HttpException
     */
    public function storeLigne(Request $request, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, TypeProjetSection $section, EntrevueConcept $concept): JsonResponse
    {
        $this->autoriserEtVerifier($classe, $groupe, $typeProjet, $section);

        $projet = $this->trouverProjet($groupe, $typeProjet);

        abort_if($concept->projet_id !== $projet->id || $concept->section_id !== $section->id, 404);
        abort_if($projet->verrouille, 403, 'Ce document est verrouillé.');
        abort_if(! $projet->peutEtreRemis(), 422, 'Ce travail a déjà été remis.');

        $ordre = ($concept->lignes()->max('ordre') ?? 0) + 1;

        $ligne = EntrevueLigne::create([
            'concept_id' => $concept->id,
            'dimension' => null,
            'indicateur' => null,
            'questions' => [],
            'ordre' => $ordre,
        ]);

        return response()->json([
            'message' => 'created',
            'ligne' => $ligne->only('id', 'ordre', 'dimension', 'indicateur', 'questions'),
        ], 201);
    }

    /**
     * Met à jour les champs d'une ligne d'entrevue.
     *
     * @throws HttpException
     */
    public function updateLigne(Request $request, Classe $classe, Groupe $groupe, TypeProjet $typeProjet, TypeProjetSection $section, EntrevueConcept $concept, EntrevueLigne $ligne): JsonResponse
    {
        $this->autoriserEtVerifier($classe, $groupe, $typeProjet, $section);

        $projet = $this->trouverProjet($groupe, $typeProjet);

        abort_if($concept->projet_id !== $projet->id || $concept->section_id !== $section->id, 404);
        abort_if($ligne->concept_id !== $concept->id, 404);
        abort_if($projet->verrouille, 403, 'Ce document est verrouillé.');
        abort_if(! $projet->peutEtreRemis(), 422, 'Ce travail a déjà été remis.');

        $validated = $request->validate([
            'dimension' => ['nullable', 'string'],
            'indicateur' => ['nullable', 'string'],
            'questions' => ['nullable', 'array'],
            'questions.*' => ['string'],
        ]);

        $ligne->update($validated);

        return response()->json(['message' => 'saved']);
    }

    /**
     * Supprime une ligne d'entrevue et réordonne les suivantes.
     *
     * @throws HttpException
     */
    public function destroyLigne(Classe $classe, Groupe $groupe, TypeProjet $typeProjet, TypeProjetSection $section, EntrevueConcept $concept, EntrevueLigne $ligne): JsonResponse
    {
        $this->autoriserEtVerifier($classe, $groupe, $typeProjet, $section);

        $projet = $this->trouverProjet($groupe, $typeProjet);

        abort_if($concept->projet_id !== $projet->id || $concept->section_id !== $section->id, 404);
        abort_if($ligne->concept_id !== $concept->id, 404);
        abort_if($projet->verrouille, 403, 'Ce document est verrouillé.');
        abort_if(! $projet->peutEtreRemis(), 422, 'Ce travail a déjà été remis.');

        $ligne->delete();

        $concept->lignes()->orderBy('ordre')->each(function (EntrevueLigne $l, int $index): void {
            $l->update(['ordre' => $index + 1]);
        });

        return response()->json(['message' => 'deleted']);
    }

    // ─── Méthodes privées ─────────────────────────────────────────────────────

    /**
     * Vérifie les autorisations et la cohérence des paramètres d'URL.
     *
     * - Groupe appartient à la classe
     * - TypeProjet appartient à l'enseignant de la classe (anti-IDOR)
     * - Section appartient au TypeProjet
     * - Section est de type 'entrevue'
     * - L'utilisateur est membre du groupe ou enseignant
     *
     * @throws HttpException
     */
    private function autoriserEtVerifier(Classe $classe, Groupe $groupe, TypeProjet $typeProjet, TypeProjetSection $section): void
    {
        abort_if($groupe->classe_id !== $classe->id, 404);
        abort_if($typeProjet->enseignant_id !== $classe->enseignant_id, 404);
        abort_if($section->type_projet_id !== $typeProjet->id, 404);
        abort_if($section->type !== 'entrevue', 422, 'Cette section n\'est pas de type entrevue.');

        $groupe->load('classe');
        $this->authorize('manageThematiques', $groupe);
    }

    /**
     * Retourne le ProjetRecherche ou lève une 404.
     *
     * @throws HttpException
     */
    private function trouverProjet(Groupe $groupe, TypeProjet $typeProjet): ProjetRecherche
    {
        return ProjetRecherche::where('groupe_id', $groupe->id)
            ->where('type_projet_id', $typeProjet->id)
            ->firstOrFail();
    }

    /**
     * Formate un concept avec ses lignes pour la réponse JSON.
     *
     * @return array<string, mixed>
     */
    private function formaterConcept(EntrevueConcept $concept): array
    {
        return [
            'id' => $concept->id,
            'label' => $concept->label,
            'ordre' => $concept->ordre,
            'lignes' => [],
        ];
    }
}
