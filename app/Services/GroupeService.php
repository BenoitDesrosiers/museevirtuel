<?php

namespace App\Services;

use App\Exceptions\TransactionEchoueeException;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\Thematique;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Règles métier de la composition d'un groupe : création, membres et thématiques.
 *
 * Les violations de règles lèvent une ValidationException ; Laravel la convertit
 * en redirection avec erreurs, comme le faisait back()->withErrors() dans les contrôleurs.
 */
class GroupeService
{
    /**
     * Crée un groupe dans la classe avec son créateur, ses membres et ses thématiques.
     *
     * Les membres non inscrits dans la classe ou déjà dans un autre groupe sont ignorés.
     *
     * @param  array<int, int|string>  $membres
     * @param  array<int, int|string>  $thematiques
     *
     * @throws ValidationException
     * @throws TransactionEchoueeException
     */
    public function creer(User $createur, Cours $cours, Classe $classe, array $membres, array $thematiques): Groupe
    {
        $inclutCreateur = $createur->isEtudiant();
        $dejaDansGroupe = $inclutCreateur && $classe->groupes()
            ->whereHas('membres', fn ($q) => $q->where('users.id', $createur->id))
            ->exists();

        if ($dejaDansGroupe) {
            throw ValidationException::withMessages(['general' => __('groupe.already_member')]);
        }

        $membresInscrits = $classe->etudiants()
            ->whereIn('users.id', $membres)
            ->pluck('users.id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        // Exclure les membres déjà dans un autre groupe de la même classe
        $membresInscrits = array_diff($membresInscrits, $classe->etudiantsDejaGroupes());

        // Un utilisateur non étudiant crée le groupe sans devenir membre étudiant.
        $tousLesMembres = array_values(array_unique(array_merge(
            $inclutCreateur ? [(int) $createur->id] : [],
            $membresInscrits,
        )));

        $this->verifierTailleEquipe($cours, count($tousLesMembres), inclutCreateur: $inclutCreateur);

        $thematiquesValides = $this->thematiquesValides($cours, $thematiques);

        try {
            $groupe = DB::transaction(function () use ($createur, $classe, $tousLesMembres, $thematiquesValides) {
                $nouveau = Groupe::create([
                    'classe_id' => $classe->id,
                    'created_by' => $createur->id,
                ]);

                $nouveau->membres()->attach($tousLesMembres);
                $nouveau->thematiques()->attach($thematiquesValides);

                return $nouveau;
            });
        } catch (QueryException $e) {
            report($e);

            throw TransactionEchoueeException::depuis($e);
        }

        return $groupe;
    }

    /**
     * Ajoute et retire des membres du groupe en respectant la taille d'équipe du cours.
     *
     * L'utilisateur qui fait la modification ne peut pas se retirer lui-même.
     *
     * @param  array<int, int|string>  $ajouter
     * @param  array<int, int|string>  $retirer
     *
     * @throws ValidationException
     * @throws TransactionEchoueeException
     */
    public function modifierMembres(User $auteur, Cours $cours, Classe $classe, Groupe $groupe, array $ajouter, array $retirer): void
    {
        $aAjouter = [];

        if ($ajouter !== []) {
            $aAjouter = $classe->etudiants()
                ->whereIn('users.id', $ajouter)
                ->pluck('users.id')
                ->map(fn ($id) => (int) $id)
                ->toArray();

            // Exclure les étudiants déjà dans un autre groupe de la même classe
            $aAjouter = array_values(array_diff($aAjouter, $classe->etudiantsDejaGroupes($groupe->id)));
        }

        $aRetirer = array_values(array_diff(array_map('intval', $retirer), [(int) $auteur->id]));

        $membresCourants = $groupe->membres()->pluck('users.id')->map(fn ($id) => (int) $id)->toArray();
        $nouveauxMembres = array_unique(array_diff(array_merge($membresCourants, $aAjouter), $aRetirer));

        $this->verifierTailleEquipe($cours, count($nouveauxMembres), inclutCreateur: false);

        try {
            DB::transaction(function () use ($aAjouter, $aRetirer, $groupe) {
                if ($aAjouter !== []) {
                    $groupe->membres()->syncWithoutDetaching($aAjouter);
                }

                if ($aRetirer !== []) {
                    $groupe->membres()->detach($aRetirer);
                }
            });
        } catch (QueryException $e) {
            report($e);

            throw TransactionEchoueeException::depuis($e);
        }
    }

    /**
     * Remplace les thématiques du groupe par celles visibles de l'enseignant du cours.
     *
     * @param  array<int, int|string>  $thematiques
     *
     * @throws ValidationException
     * @throws TransactionEchoueeException
     */
    public function synchroniserThematiques(Cours $cours, Groupe $groupe, array $thematiques): void
    {
        $thematiquesValides = $this->thematiquesValides($cours, $thematiques);

        try {
            DB::transaction(function () use ($groupe, $thematiquesValides) {
                $groupe->thematiques()->sync($thematiquesValides);
            });
        } catch (QueryException $e) {
            report($e);

            throw TransactionEchoueeException::depuis($e);
        }
    }

    /**
     * Vérifie les bornes de taille d'équipe configurées sur le cours.
     *
     * @throws ValidationException
     */
    private function verifierTailleEquipe(Cours $cours, int $totalMembres, bool $inclutCreateur): void
    {
        $message = match (true) {
            // Un groupe vide n'a pas de sens, même si le cours ne fixe aucun minimum.
            $totalMembres < 1 => __('groupe.taille_min', ['min' => 1]),
            $cours->taille_equipe_min !== null && $totalMembres < $cours->taille_equipe_min => __(
                $inclutCreateur ? 'groupe.taille_min_createur' : 'groupe.taille_min',
                ['min' => $cours->taille_equipe_min],
            ),
            $cours->taille_equipe_max !== null && $totalMembres > $cours->taille_equipe_max => __('groupe.taille_max', ['max' => $cours->taille_equipe_max]),
            default => null,
        };

        if ($message !== null) {
            throw ValidationException::withMessages(['membres' => $message]);
        }
    }

    /**
     * Filtre les thématiques demandées sur celles visibles par l'enseignant du cours (au moins une).
     *
     * @param  array<int, int|string>  $thematiques
     * @return list<int>
     *
     * @throws ValidationException
     */
    private function thematiquesValides(Cours $cours, array $thematiques): array
    {
        $valides = Thematique::visiblesPar($cours->enseignant)
            ->whereIn('id', $thematiques)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->toArray();

        if ($valides === []) {
            throw ValidationException::withMessages(['thematiques' => __('groupe.thematique_requise')]);
        }

        return $valides;
    }
}
