<?php

namespace App\Services;

use App\Http\Resources\VisioConferenceResource;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\GroupeVideo;
use App\Models\ProjetRecherche;
use App\Models\Thematique;
use App\Models\TypeProjet;
use App\Models\User;
use App\Models\VisioConference;
use Illuminate\Support\Collection;

/**
 * Construit les props Inertia de la page de détail d'un groupe.
 */
class GroupeProps
{
    /**
     * Retourne les props de la page Groupes/Show pour l'utilisateur donné.
     *
     * @return array<string, mixed>
     */
    public function pourAffichage(Cours $cours, Classe $classe, Groupe $groupe, User $user): array
    {
        $groupe->load(['membres', 'thematiques', 'notes.auteur', 'notes.corrections',
            'createur', 'medias.auteur', 'temoin']);

        // Il faudra retirer la portion $user->isEnseignant() && en production.
        $estEnseignant = $user->isEnseignant() && $cours->enseignant_id === $user->id;

        return [
            'groupe' => $groupe,
            'classe' => $classe->only('id', 'code', 'cours_id'),
            'cours' => $cours->only('id', 'nom_cours', 'code', 'groupe'),
            'estMembre' => $groupe->membres->contains('id', $user->id),
            'estEnseignant' => $estEnseignant,
            'estTemoin' => $groupe->personne_agee_id === $user->id,
            'estCreateur' => $groupe->created_by === $user->id,
            'thematiquesDispo' => Thematique::visiblesPar($cours->enseignant)->get(['id', 'nom', 'periode_historique']),
            'etudiantsDispo' => $this->etudiantsDispo($classe, $groupe),
            ...$this->temoins($groupe, $estEnseignant || $user->isAdmin()),
            'visioConferences' => $this->visioConferences($cours, $groupe),
            'videos' => $this->videos($groupe, $user, $estEnseignant),
            'peutTranscrireMedia' => $user->can('transcrireMedia', $groupe),
            ...$this->projetMusee($cours, $groupe),
        ];
    }

    /**
     * Retourne les étudiants de la classe qui ne sont ni membres de ce groupe ni dans un autre.
     */
    private function etudiantsDispo(Classe $classe, Groupe $groupe): Collection
    {
        return $classe->etudiants()
            ->whereNotIn('users.id', $groupe->membres->pluck('id'))
            ->whereNotIn('users.id', $classe->etudiantsDejaGroupes($groupe->id))
            ->get(['users.id', 'prenom', 'nom']);
    }

    /**
     * Retourne tous les témoins actifs et ceux suggérés selon les thématiques du groupe.
     *
     * Seuls l'enseignant du cours et les admins gèrent le témoin : les autres reçoivent des listes vides.
     *
     * @return array{temoinsDisponibles: Collection, tousLesTemoins: Collection}
     */
    private function temoins(Groupe $groupe, bool $peutGererTemoin): array
    {
        if (! $peutGererTemoin) {
            return ['temoinsDisponibles' => collect(), 'tousLesTemoins' => collect()];
        }

        $tousLesTemoins = User::where('role', 'personne_agee')
            ->where('statut', 'actif')
            ->with('thematiquesChoisies:id')
            ->orderBy('nom')
            ->get(['id', 'prenom', 'nom']);

        $groupeThematiqueIds = $groupe->thematiques->pluck('id');
        $temoinsDisponibles = $groupeThematiqueIds->isEmpty()
            ? $tousLesTemoins
            : $tousLesTemoins->filter(fn (User $t) => $t->thematiquesChoisies->pluck('id')
                ->intersect($groupeThematiqueIds)->isNotEmpty())->values();

        $tousLesTemoins->each(fn (User $t) => $t->makeHidden('thematiquesChoisies'));

        return ['temoinsDisponibles' => $temoinsDisponibles, 'tousLesTemoins' => $tousLesTemoins];
    }

    /**
     * Retourne les visios ciblées sur le groupe ou ouvertes à tout le cours.
     *
     * @return array<int, array<string, mixed>>
     */
    private function visioConferences(Cours $cours, Groupe $groupe): array
    {
        return VisioConference::where('cours_id', $cours->id)
            ->where(fn ($q) => $q->whereNull('groupe_id')->orWhere('groupe_id', $groupe->id))
            ->with('animateur:id,prenom,nom')
            ->orderByDesc('created_at')
            ->get()
            ->pipe(fn ($visios) => VisioConferenceResource::collection($visios)->resolve());
    }

    /**
     * Retourne les vidéos du groupe visibles par l'utilisateur.
     */
    private function videos(Groupe $groupe, User $user, bool $estEnseignant): Collection
    {
        return GroupeVideo::where('groupe_id', $groupe->id)
            ->VisiblesPour($user, $estEnseignant)
            ->with('auteur:id,prenom,nom')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Retourne le projet musée du groupe, exposé seulement si le cours a un TypeProjet de type 'musee'.
     *
     * @return array{typeProjetMusee: array<string, mixed>|null, projetMusee: array<string, mixed>|null}
     */
    private function projetMusee(Cours $cours, Groupe $groupe): array
    {
        $typeProjetMusee = TypeProjet::where('cours_id', $cours->id)
            ->where('type', 'musee')
            ->first(['id', 'nom']);

        $projetMusee = $typeProjetMusee
            ? ProjetRecherche::where('groupe_id', $groupe->id)
                ->where('type_projet_id', $typeProjetMusee->id)
                ->with('museePublication:projet_recherche_id,est_publie')
                ->first(['id', 'type_projet_id', 'groupe_id'])
            : null;

        return [
            'typeProjetMusee' => $typeProjetMusee?->only('id', 'nom'),
            'projetMusee' => $projetMusee ? [
                'type_projet_id' => $projetMusee->type_projet_id,
                'est_publie' => (bool) $projetMusee->museePublication?->est_publie,
            ] : null,
        ];
    }
}
