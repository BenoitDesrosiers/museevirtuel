<?php

namespace App\Services;

use App\Models\Classe;
use App\Models\Cours;
use App\Models\EcheancierEtudiantProgress;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Construit les props Inertia de la page d'une classe (section).
 */
class ClasseProps
{
    /**
     * Retourne les props de la page Classes/Show.
     *
     * Ceux qui gèrent la classe (enseignant du cours, admin) reçoivent les types de projets
     * (boutons d'aperçu des notes) et la liste des inscrits ; l'étudiant reçoit l'échéancier, les documents, les objectifs et les références.
     *
     * @return array<string, mixed>
     */
    public function pourShow(Cours $cours, Classe $classe, User $user, bool $peutGererClasse): array
    {
        $classe->load([
            'groupes.membres',
            'groupes.thematiques',
            'groupes.temoin',
            'etudiants' => fn ($query) => $query->withPivot('statut_cours'),
        ]);

        return [
            'cours' => $cours->only('id', 'nom_cours', 'code', 'groupe'),
            'classe' => $this->classe($classe, $user, $peutGererClasse),
            'utilisateurConnecteId' => $user->id,
            'estEnseignant' => $peutGererClasse,
            'typesProjets' => $peutGererClasse ?
                $cours->typesProjets()->get(['id', 'nom', 'ponderation', 'is_sommatif']) : collect(),
            'echeancierEtapes' => $peutGererClasse ? collect() : $this->echeancierEtapes($cours, $user),
            'documents' => $peutGererClasse ? collect() : $this->documents($cours),
            'objectifs' => $peutGererClasse ? collect() : $this->objectifs($cours),
            'references' => $peutGererClasse ? collect() : $this->references($cours),
        ];
    }

    /**
     * Sérialise la classe champ par champ : les props Inertia sont lisibles dans le navigateur,
     *
     * @return array<string, mixed>
     */
    private function classe(Classe $classe, User $user, bool $peutGererClasse): array
    {
        // Numéro d'un groupe = son rang par id dans la classe (comme Groupe::getNumeroAttribute, sans requête par groupe)
        $numeros = $classe->groupes->pluck('id')->sort()->values()->flip()->map(fn ($rang) => $rang + 1);

        $groupes = $peutGererClasse
            ? $classe->groupes
            : $classe->groupes->filter(fn ($groupe) => $groupe->membres->contains('id', $user->id));

        return [
            ...$classe->only('id', 'numero', 'code', 'nom', 'jour_semaine', 'plage_horaire', 'cours_id'),
            'groupes' => $groupes->map(fn ($groupe) => [
                ...$groupe->only('id', 'classe_id', 'created_by'),
                'numero' => $numeros[$groupe->id],
                'membres' => $groupe->membres->map->only('id', 'prenom', 'nom')->values(),
                'thematiques' => $groupe->thematiques->map->only('id', 'nom')->values(),
                'temoin' => $groupe->temoin?->only('id', 'prenom', 'nom'),
            ])->values(),
            'etudiants' => $peutGererClasse ? $classe->etudiants->map(fn ($etudiant) => [
                ...$etudiant->only('id', 'prenom', 'nom', 'email', 'no_da'),
                'pivot' => ['statut_cours' => $etudiant->pivot->statut_cours],
            ])->values() : collect(),
        ];
    }

    /**
     * Retourne les étapes de l'échéancier avec la progression personnelle de l'étudiant.
     */
    private function echeancierEtapes(Cours $cours, User $user): Collection
    {
        $etapes = $cours->echeancierEtapes()->get();
        $progressions = EcheancierEtudiantProgress::whereIn('echeancier_etape_id', $etapes->pluck('id'))
            ->where('user_id', $user->id)
            ->pluck('is_done', 'echeancier_etape_id');

        return $etapes->map(fn ($etape) => [
            'id' => $etape->id,
            'semaine' => $etape->semaine,
            'etape' => $etape->etape,
            'is_done' => $etape->is_done,
            'ordre' => $etape->ordre,
            'etudiant_done' => (bool) ($progressions[$etape->id] ?? false),
        ]);
    }

    /**
     * Retourne les documents déposés par l'enseignant pour le cours.
     */
    private function documents(Cours $cours): Collection
    {
        return $cours->documents()->get()->map(fn ($doc) => [
            'id' => $doc->id,
            'nom_original' => $doc->nom_original,
            'url' => $doc->url,
            'type' => $doc->type,
            'taille' => $doc->taille,
        ]);
    }

    /**
     * Retourne les objectifs du cours dans leur ordre d'affichage.
     */
    private function objectifs(Cours $cours): Collection
    {
        return $cours->objectifs()->orderBy('ordre')->get()->map(fn ($o) => [
            'id' => $o->id,
            'contenu' => $o->contenu,
            'ordre' => $o->ordre,
        ]);
    }

    /**
     * Retourne les références (liens) du cours.
     */
    private function references(Cours $cours): Collection
    {
        return $cours->references()->get()->map(fn ($r) => [
            'id' => $r->id,
            'nom' => $r->nom,
            'url' => $r->url,
            'ordre' => $r->ordre,
        ]);
    }
}
