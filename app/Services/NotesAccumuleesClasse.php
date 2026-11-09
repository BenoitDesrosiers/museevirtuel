<?php

namespace App\Services;

use App\Models\Classe;
use App\Models\Cours;
use App\Models\TypeProjet;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Construit les props Inertia de l'aperçu des notes accumulées (pondérées) d'une classe.
 */
readonly class NotesAccumuleesClasse
{
    /**
     * Injecte le service de calcul des notes.
     */
    public function __construct(private CalculNotesService $calculNotesService) {}

    /**
     * Retourne les props de la page Classes/ApercuNotesAccumulees.
     *
     * Une ligne par étudiant avec sa note par type de projet et le total pondéré :
     * Σ(noteFinale × pondération / 100).
     *
     * @return array<string, mixed>
     */
    public function pourClasse(Cours $cours, Classe $classe): array
    {
        $typesProjets = $this->typesProjetsPonderes($cours);

        $classe->load([
            'groupes.membres',
            'groupes.projets' => fn ($q) => $q->whereIn('type_projet_id', $typesProjets->pluck('id')),
            'groupes.projets.critereCorrections',
            'groupes.projets.annotations',
        ]);

        $lignes = collect();

        foreach ($classe->groupes as $groupe) {
            $projetParType = $groupe->projets->keyBy('type_projet_id');

            foreach ($groupe->membres as $membre) {
                $lignes->push($this->ligne($membre, $typesProjets, $projetParType));
            }
        }

        return [
            'cours' => $cours->only('id', 'nom_cours'),
            'classe' => ['id' => $classe->id, 'numero' => $classe->numero, 'nom' => $classe->nom],
            'typesProjets' => $typesProjets->map(fn (TypeProjet $tp) => [
                'id' => $tp->id,
                'nom' => $tp->nom,
                'ponderation' => (float) $tp->ponderation,
            ])->values(),
            'lignes' => $lignes->values(),
            'sommePonderations' => (float) $typesProjets->sum('ponderation'),
        ];
    }

    /**
     * Retourne les types de projets sommatifs du cours ayant une pondération définie.
     *
     * Les projets non sommatifs n'ont pas de poids sur la note finale.
     */
    private function typesProjetsPonderes(Cours $cours): Collection
    {
        return $cours->typesProjets()
            ->where('is_sommatif', true)
            ->whereNotNull('ponderation')
            ->orderBy('nom')
            ->with('criteres')
            ->get();
    }

    /**
     * Construit la ligne d'un étudiant : sa note par type de projet et son total pondéré.
     *
     * Un type de projet sans projet (ou sans critère positif) vaut null et ne compte pas dans le total.
     *
     * @return array<string, mixed>
     */
    private function ligne(User $membre, Collection $typesProjets, Collection $projetParType): array
    {
        $notesParType = [];
        $total = 0.0;

        foreach ($typesProjets as $tp) {
            $projet = $projetParType->get($tp->id);

            $note = $projet === null ? null : $this->calculNotesService->pourcentage(
                $tp->criteres, $projet->critereCorrections, $projet->annotations, $membre->id);

            $notesParType[$tp->id] = $note;

            if ($note !== null) {
                $total += $note * ((float) $tp->ponderation / 100);
            }
        }

        return [
            'da' => preg_replace('/\D/', '', (string) $membre->no_da),
            'prenom' => $membre->prenom,
            'nom' => $membre->nom,
            'notes_par_type' => $notesParType,
            'total' => round($total, 2),
        ];
    }
}
