<?php

namespace App\Services;

use App\Enums\StatutProjetRecherche;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\MuseePublication;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Construit les props Inertia de la liste des projets d'un groupe.
 */
class ProjetRechercheIndexProps
{
    /**
     * Retourne les props de la page Projets/Index : une carte par type de projet du cours,
     * avec le projet du groupe (ou null s'il n'est pas encore créé).
     *
     * @return array<string, mixed>
     */
    public function pourGroupe(Cours $cours, Classe $classe, Groupe $groupe,
        User $user, bool $estEnseignant): array
    {
        $typesProjets = $this->typesProjetsVisibles($cours, $user, $estEnseignant);
        $projetsParType = $this->projetsParType($groupe, $typesProjets);

        return [
            'groupe' => $groupe->only('id', 'code', 'classe_id'),
            'classe' => $classe->only('id', 'code', 'cours_id'),
            'cours' => $cours->only('id', 'nom_cours', 'code', 'groupe'),
            'projets' => $typesProjets->map(fn (TypeProjet $typeProjet) => $this->carte(
                $typeProjet, $projetsParType->get($typeProjet->id), $groupe->membres)),
            'estEnseignant' => $estEnseignant,
        ];
    }

    /**
     * Retourne les types de projets du cours (pas de tous les cours de l'enseignant).
     *
     * Les étudiants ne voient que les types rendus accessibles par l'enseignant.
     */
    private function typesProjetsVisibles(Cours $cours, User $user, bool $estEnseignant): Collection
    {
        return TypeProjet::where('cours_id', $cours->id)
            ->when(! $estEnseignant && ! $user->isAdmin(), fn ($q) => $q->where('accessible', true))
            ->get();
    }

    /**
     * Précharge tous les projets du groupe en une seule requête (évite le N+1), indexés par type.
     */
    private function projetsParType(Groupe $groupe, Collection $typesProjets): Collection
    {
        return ProjetRecherche::where('groupe_id', $groupe->id)
            ->whereIn('type_projet_id', $typesProjets->pluck('id'))
            ->with(['typeProjet', 'conclusions', 'museePublication'])
            ->get()
            ->keyBy('type_projet_id');
    }

    /**
     * Construit la carte d'un type de projet avec l'état du projet et des conclusions.
     *
     * @return array<string, mixed>
     */
    private function carte(TypeProjet $typeProjet, ?ProjetRecherche $projet, Collection $membres): array
    {
        return [
            'typeProjet' => array_merge(
                $typeProjet->only('id', 'nom', 'description', 'accessible'),
                ['type' => $typeProjet->type],
            ),
            'projet' => $projet ? $this->resumeProjet($typeProjet, $projet) : null,
            'statut' => $projet?->statutActuel()->value
                ?? StatutProjetRecherche::fromDates($typeProjet->date_remise, null)->value,
            'conclusions' => $this->etatConclusions($projet, $membres),
        ];
    }

    /**
     * Résume le projet pour la carte : titre, complétion, statut et statut de publication (musée).
     *
     * @return array<string, mixed>
     */
    private function resumeProjet(TypeProjet $typeProjet, ProjetRecherche $projet): array
    {
        return [
            'id' => $projet->id,
            'titre_projet' => $projet->titre_projet,
            'completion' => $projet->completion(),
            'statut' => $projet->synchroniserStatut()->value,
            'statut_publication' => $typeProjet->isMusee()
                ? ($projet->museePublication?->statut ?? MuseePublication::STATUT_BROUILLON) : null,
        ];
    }

    /**
     * Indique pour chaque membre s'il a rédigé sa conclusion (contenu non vide une fois le HTML retiré).
     */
    private function etatConclusions(?ProjetRecherche $projet, Collection $membres): Collection
    {
        $conclusionsParMembre = $projet ? $projet->conclusions->keyBy('user_id') : collect();

        return $membres->map(function (User $membre) use ($conclusionsParMembre): array {
            $conclusion = $conclusionsParMembre->get($membre->id);

            return [
                'etudiant' => $membre->only('id', 'prenom', 'nom'),
                'a_redige' => $conclusion !== null && trim(strip_tags((string) ($conclusion->contenu ?? ''))) !== '',
            ];
        });
    }
}
