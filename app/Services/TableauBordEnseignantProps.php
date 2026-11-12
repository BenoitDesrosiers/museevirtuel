<?php

namespace App\Services;

use App\Http\Resources\VisioConferenceResource;
use App\Models\Cours;
use App\Models\ProjetRecherche;
use App\Models\Thematique;
use App\Models\User;
use App\Models\VisioConference;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Construit les props Inertia du tableau de bord de l'enseignant.
 */
class TableauBordEnseignantProps
{
    /**
     * Retourne les props de la page Enseignant/Index pour l'enseignant donné.
     *
     * @return array<string, mixed>
     */
    public function pourEnseignant(User $user): array
    {
        return [
            'cours' => $this->cours($user),
            'thematiques' => $this->thematiques($user),
            'travauxRemis' => $this->travauxRemis($user),
            'temoinsEnAttente' => $this->temoinsEnAttente($user),
            'temoinsApprouves' => $this->temoinsApprouves($user),
            'prochainesVisios' => $this->prochainesVisios($user),
        ];
    }

    /**
     * Retourne les cours de l'enseignant avec leur nombre de classes et d'étudiants distincts.
     */
    private function cours(User $user): Collection
    {
        $cours = $user->cours()
            ->withCount('classes')
            ->with('classes.etudiants:id')
            ->orderBy('nom_cours')
            ->get();

        $cours->each(function (Cours $cours): void {
            $etudiantsIds = $cours->classes
                ->flatMap(fn ($classe) => $classe->etudiants->pluck('id'))
                ->unique();

            $cours->setAttribute('etudiants_count', $etudiantsIds->count());
            $cours->unsetRelation('classes');
        });

        return $cours;
    }

    /**
     * Retourne les thématiques visibles par l'enseignant.
     */
    private function thematiques(User $user): Collection
    {
        return Thematique::visiblesPar($user)
            ->with('enseignant:id,prenom,nom')
            ->orderBy('nom')
            ->get();
    }

    /**
     * Retourne les 10 travaux les plus récemment remis parmi les groupes de l'enseignant.
     */
    private function travauxRemis(User $user): Collection
    {
        return ProjetRecherche::whereNotNull('remis_le')
            ->whereHas('groupe.classe.cours', fn ($q) => $q->where('enseignant_id', $user->id))
            ->with(['groupe' => fn ($q) => $q->with(['membres', 'classe.cours'])])
            ->orderByDesc('remis_le')
            ->limit(10)
            ->get()
            ->map(fn (ProjetRecherche $projet) => [
                'id' => $projet->id,
                'titre_projet' => $projet->titre_projet,
                'remis_le' => $projet->remis_le->toIso8601String(),
                'classe' => [
                    'id' => $projet->groupe->id,
                    'numero' => $projet->groupe->numero,
                    'cours_id' => $projet->groupe->classe->cours_id,
                ],
                'membres' => $projet->groupe->membres
                    ->map->only('id', 'prenom', 'nom')
                    ->values(),
            ]);
    }

    /**
     * Retourne les témoins en attente liés à au moins une thématique visible par l'enseignant
     * (celles de son cégep, ou les siennes s'il n'en a pas).
     */
    private function temoinsEnAttente(User $user): Collection
    {
        $thematiqueIds = Thematique::visiblesPar($user)->pluck('id');

        return $this->queryTemoinsParThematiques($thematiqueIds)
            ->enAttente()
            ->get(['id', 'prenom', 'nom', 'email', 'description', 'created_at']);
    }

    /**
     * Retourne les témoins actifs approuvés par cet enseignant spécifiquement.
     */
    private function temoinsApprouves(User $user): Collection
    {
        return User::where('role', 'personne_agee')
            ->where('approuve_par_id', $user->id)
            ->actif()
            ->with('thematiquesChoisies:id,nom')
            ->orderBy('created_at')
            ->get(['id', 'prenom', 'nom', 'email', 'description', 'created_at']);
    }

    /**
     * Retourne les 10 prochaines visioconférences planifiées dans les cours de l'enseignant.
     *
     * @return array<int, mixed>
     */
    private function prochainesVisios(User $user): array
    {
        return VisioConference::whereHas('cours', fn ($q) => $q->where('enseignant_id', $user->id))
            ->whereNull('ended_at')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>=', now())
            ->with(['cours:id,nom_cours,code,groupe', 'groupe', 'animateur:id,prenom,nom'])
            ->orderBy('scheduled_at')
            ->limit(10)
            ->get()
            ->pipe(fn ($visios) => VisioConferenceResource::collection($visios)->resolve());
    }

    /**
     * Retourne une base de requête pour les témoins liés aux thématiques données.
     */
    private function queryTemoinsParThematiques(Collection $thematiqueIds): Builder
    {
        return User::where('role', 'personne_agee')
            ->whereHas('thematiquesChoisies', fn ($q) => $q->whereIn('thematiques.id', $thematiqueIds))
            ->with('thematiquesChoisies:id,nom')
            ->orderBy('created_at');
    }
}
