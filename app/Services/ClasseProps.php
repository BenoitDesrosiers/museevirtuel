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
     * L'enseignant reçoit les types de projets (boutons d'aperçu des notes) ;
     * l'étudiant reçoit l'échéancier, les documents, les objectifs et les références.
     *
     * @return array<string, mixed>
     */
    public function pourShow(Cours $cours, Classe $classe, User $user, bool $estEnseignant): array
    {
        $classe->load([
            'groupes.membres',
            'groupes.thematiques',
            'groupes.temoin',
            'etudiants' => fn ($query) => $query->withPivot('statut_cours'),
        ]);

        return [
            'cours' => $cours->only('id', 'nom_cours', 'code', 'groupe'),
            'classe' => $classe,
            'utilisateurConnecteId' => $user->id,
            'estEnseignant' => $estEnseignant,
            'typesProjets' => $estEnseignant ?
                $cours->typesProjets()->get(['id', 'nom', 'ponderation', 'is_sommatif']) : collect(),
            'echeancierEtapes' => $estEnseignant ? collect() : $this->echeancierEtapes($cours, $user),
            'documents' => $estEnseignant ? collect() : $this->documents($cours),
            'objectifs' => $estEnseignant ? collect() : $this->objectifs($cours),
            'references' => $estEnseignant ? collect() : $this->references($cours),
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
