<?php

namespace App\Services;

use App\Http\Resources\VisioConferenceResource;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetRecherche;
use App\Models\User;
use App\Models\VisioConference;
use Illuminate\Support\Collection;

/**
 * Construit les props Inertia du tableau de bord de l'étudiant.
 */
class TableauBordEtudiantProps
{
    /**
     * Retourne les props de la page Cours/Index pour l'étudiant donné.
     *
     * Les cours verrouillés par l'enseignant sont masqués.
     *
     * @return array<string, mixed>
     */
    public function pourEtudiant(User $user): array
    {
        $cours = $user->coursInscrits()
            ->where('is_verrouille', false)
            ->with('enseignant:id,prenom,nom')
            ->orderBy('nom_cours')
            ->get();

        return [
            'cours' => $this->cours($user, $cours),
            'projets' => $this->projets($user),
            'prochainesVisios' => $this->prochainesVisios($user, $cours->pluck('id')),
            'mesReferences' => $this->mesReferences($user),
            'zoteroConfig' => $this->zoteroConfig($user),
        ];
    }

    /**
     * Retourne les cours avec la classe (section) de l'étudiant, pour lier directement sa page de classe.
     */
    private function cours(User $user, Collection $cours): Collection
    {
        $classesParCours = $user->classesInscrites()
            ->whereIn('classes.cours_id', $cours->pluck('id'))
            ->get(['classes.id', 'classes.cours_id'])
            ->keyBy('cours_id');

        return $cours->map(fn (Cours $c) => [
            ...$c->only('id', 'nom_cours', 'description', 'code', 'groupe', 'annee', 'session'),
            'enseignant' => $c->enseignant->only('id', 'prenom', 'nom'),
            'classe_id' => $classesParCours->get($c->id)?->id,
        ]);
    }

    /**
     * Retourne les projets de l'étudiant via ses groupes, avec de quoi construire leur URL.
     */
    private function projets(User $user): Collection
    {
        return $user->groupesMembre()
            ->with(['classe:id,cours_id', 'projets' => fn ($q) => $q->with('typeProjet:id,nom')])
            ->get()
            ->flatMap(fn (Groupe $groupe): Collection => $groupe->projets->map(fn (ProjetRecherche $projet): array => [
                'id' => $projet->id,
                'titre' => $projet->titre_projet,
                'type_projet' => $projet->typeProjet->only('id', 'nom'),
                'groupe_id' => $groupe->id,
                'classe_id' => $groupe->classe_id,
                'cours_id' => $groupe->classe->cours_id,
            ]))->values();
    }

    /**
     * Retourne les 10 prochaines visios planifiées, ouvertes à tout le cours ou ciblées sur un groupe de l'étudiant.
     *
     * @param  Collection<int, int>  $coursIds
     * @return array<int, array<string, mixed>>
     */
    private function prochainesVisios(User $user, Collection $coursIds): array
    {
        $groupeIds = $user->groupesMembre()->pluck('groupes.id');

        return VisioConference::whereIn('cours_id', $coursIds)
            ->where(fn ($q) => $q->whereNull('groupe_id')->orWhereIn('groupe_id', $groupeIds))
            ->whereNull('ended_at')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>=', now())
            ->with(['cours:id,nom_cours', 'animateur:id,prenom,nom'])
            ->orderBy('scheduled_at')
            ->limit(10)
            ->get()
            ->pipe(fn ($visios) => VisioConferenceResource::collection($visios)->resolve());
    }

    /**
     * Retourne les références personnelles de l'étudiant.
     */
    private function mesReferences(User $user): Collection
    {
        return $user->etudiantReferences()->get()->map(fn ($r) => [
            ...$r->only('id', 'titre', 'auteurs', 'annee', 'type_source', 'url', 'doi', 'publication', 'ordre'),
            'est_depuis_zotero' => $r->estDepuisZotero(),
        ]);
    }

    /**
     * Retourne l'état de la configuration Zotero de l'étudiant.
     *
     * @return array{configure: bool, synchronise_le: string|null}
     */
    private function zoteroConfig(User $user): array
    {
        return [
            'configure' => $user->zoteroCredential !== null,
            'synchronise_le' => $user->zoteroCredential?->synchronise_le?->toIso8601String(),
        ];
    }
}
