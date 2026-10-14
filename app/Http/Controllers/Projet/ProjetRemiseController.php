<?php

namespace App\Http\Controllers\Projet;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Projet\Concerns\AutoriseProjetRecherche;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetRecherche;
use App\Models\ProjetVoteRemise;
use App\Models\TypeProjet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Remise du travail par le groupe : remise, annulation et vote des membres.
 */
class ProjetRemiseController extends Controller
{
    use AutoriseProjetRecherche;

    /**
     * Enregistre la remise du travail par l'équipe d'étudiants.
     *
     * @throws HttpException
     */
    public function store(Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet): JsonResponse
    {
        abort_if($classe->cours_id !== $cours->id, 404);
        abort_if($groupe->classe_id !== $classe->id, 404);
        $this->verifierTypeProjetAppartientCours($typeProjet, $cours);

        $groupe->loadMissing('membres');
        abort_unless($groupe->membres->contains('id', auth()->id()), 403);

        $projet = ProjetRecherche::firstOrCreate([
            'groupe_id' => $groupe->id,
            'type_projet_id' => $typeProjet->id,
        ]);

        // Associer la relation pour que peutEtreRemis() lise les paramètres depuis TypeProjet
        $projet->setRelation('typeProjet', $typeProjet);

        abort_if($projet->verrouille, 403, 'Ce document est verrouillé.');
        abort_unless($projet->peutEtreRemis(), 422, 'Ce travail a déjà été remis et les remises multiples ne sont pas autorisées.');

        $contenusManquants = $this->contenusTextuelsManquants($projet, $groupe);

        if ($contenusManquants !== []) {
            return response()->json([
                'message' => 'Le projet doit contenir les textes requis avant de pouvoir être remis.',
                'manquants' => $contenusManquants,
            ], 422);
        }

        $projet->update(['remis_le' => now()]);
        $projet->synchroniserStatut();

        return response()->json([
            'message' => 'remis',
            'remis_le' => $projet->remis_le->toIso8601String(),
        ]);
    }

    /**
     * Annule la remise du travail (enseignant seulement).
     *
     * @throws HttpException
     */
    public function destroy(Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet): JsonResponse
    {
        $this->verifierTypeProjetAppartientCours($typeProjet, $cours);
        $this->autoriserEnseignant($cours, $classe, $groupe);

        $projet = $this->trouverProjet($groupe, $typeProjet);
        $projet->setRelation('typeProjet', $typeProjet);

        DB::transaction(function () use ($projet): void {
            $projet->votes()->delete();
            $projet->update(['remis_le' => null]);
            $projet->synchroniserStatut();
        });

        return response()->json(['message' => 'remise_annulee']);
    }

    /**
     * Enregistre ou met à jour le vote de remise d'un étudiant membre du groupe.
     *
     * Si tous les membres ont voté true, la remise est enregistrée de façon atomique.
     *
     * @throws HttpException
     */
    public function voter(Request $request, Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet): JsonResponse
    {
        abort_if($classe->cours_id !== $cours->id, 404);
        abort_if($groupe->classe_id !== $classe->id, 404);
        $this->verifierTypeProjetAppartientCours($typeProjet, $cours);

        $groupe->loadMissing('membres');
        abort_unless($groupe->membres->contains('id', auth()->id()), 403);

        $projet = $this->trouverProjet($groupe, $typeProjet);
        $projet->setRelation('typeProjet', $typeProjet);

        abort_unless($projet->peutEtreRemis(), 422, 'La remise n\'est plus possible.');

        $validated = $request->validate([
            'vote' => ['required', 'boolean'],
        ]);

        ProjetVoteRemise::updateOrCreate(
            ['projet_id' => $projet->id, 'user_id' => auth()->id()],
            ['vote' => $validated['vote']],
        );

        $votes = $projet->votes()->get();
        $nbMembres = $groupe->membres->count();

        $tousOntVote = $votes->count() === $nbMembres
            && $votes->every(fn (ProjetVoteRemise $v) => $v->vote);

        if ($tousOntVote) {
            DB::transaction(function () use ($projet): void {
                $projet->refresh();

                if ($projet->remis_le === null || $projet->remises_multiples) {
                    $projet->update(['remis_le' => now()]);
                    $projet->synchroniserStatut();
                }
            });
        }

        return response()->json([
            'message' => 'vote_enregistre',
            'remis_le' => $projet->fresh()->remis_le?->toIso8601String(),
        ]);
    }

    private function ajouterSiInsuffisant(array $manquants, string $contenu, int $minimum, string $section, string $raison): array
    {
        if ($this->nombreMots($contenu) < $minimum) {
            $manquants[] = [
                'section' => $section,
                'raison' => $raison,
            ];
        }

        return $manquants;
    }

    /**
     * Retourne les contenus textuels vides ou trop courts avant une remise.
     *
     * Cette validation reprend les mêmes règles que l'éditeur frontend pour
     * empêcher qu'une requête directe contourne les contrôles de l'interface.
     *
     * @return list<array{section: string, raison: string}>
     */
    private function contenusTextuelsManquants(ProjetRecherche $projet, Groupe $groupe): array
    {
        $projet->loadMissing([
            'typeProjet.sections',
            'sectionContenus',
            'sectionParagraphes',
            'conclusions',
            'developpements',
        ]);

        $manquants = [];

        $contenusParSection = $projet->sectionContenus->keyBy('section_id');
        $paragraphesParSection = $projet->sectionParagraphes->groupBy('section_id');
        $conclusionsParSection = $projet->conclusions
            ->whereNotNull('section_id')
            ->groupBy('section_id');

        foreach ($projet->typeProjet->sections as $section) {
            $libelle = $section->label ?: 'Section '.$section->id;

            if ($section->type === 'texte') {
                $manquants = $this->ajouterSiInsuffisant(
                    $manquants,
                    (string) $contenusParSection->get($section->id)?->contenu,
                    1,
                    $libelle,
                    'Le contenu textuel est vide.',
                );
            }

            if ($section->type === 'paragraphes') {
                $paragraphes = $paragraphesParSection->get($section->id, collect());

                if ($paragraphes->isEmpty()) {
                    $manquants[] = [
                        'section' => $libelle,
                        'raison' => 'Aucun paragraphe n’a été ajouté.',
                    ];
                }

                foreach ($paragraphes as $paragraphe) {
                    if ($this->nombreMots($paragraphe->contenu) < 1) {
                        $manquants[] = [
                            'section' => "{$libelle} — paragraphe {$paragraphe->ordre}",
                            'raison' => 'Le contenu est vide.',
                        ];
                    }
                }
            }

            if ($section->type === 'individuel') {
                $conclusions = $conclusionsParSection->get($section->id, collect());

                foreach ($groupe->membres as $membre) {
                    $conclusion = $conclusions->firstWhere('user_id', $membre->id);

                    $manquants = $this->ajouterSiInsuffisant(
                        $manquants,
                        (string) $conclusion?->contenu,
                        20,
                        "{$libelle} — conclusion de {$membre->prenom} {$membre->nom}",
                        'La conclusion doit avoir au moins 20 mots.',
                    );
                }
            }
        }

        if ($projet->typeProjet->sections->isEmpty() && $projet->typeProjet->has_introduction) {
            foreach ([
                'introduction_amener' => 'Sujet amené',
                'introduction_poser' => 'Sujet posé',
                'introduction_diviser' => 'Sujet divisé',
            ] as $champ => $libelle) {
                $manquants = $this->ajouterSiInsuffisant(
                    $manquants,
                    (string) $projet->{$champ},
                    20,
                    $libelle,
                    "L'introduction doit avoir au moins 20 mots.",
                );
            }
        }

        if ($projet->typeProjet->sections->isEmpty() && $projet->typeProjet->has_conclusion_individuelle) {
            $conclusions = $projet->conclusions->whereNull('section_id');

            foreach ($groupe->membres as $membre) {
                $conclusion = $conclusions->firstWhere('user_id', $membre->id);

                $manquants = $this->ajouterSiInsuffisant(
                    $manquants,
                    (string) $conclusion?->contenu,
                    20,
                    "Conclusion de {$membre->prenom} {$membre->nom}",
                    'La conclusion doit avoir au moins 20 mots.',
                );
            }
        }

        foreach ($projet->developpements as $developpement) {
            $manquants = $this->ajouterSiInsuffisant(
                $manquants,
                (string) $developpement->titre,
                3,
                "Paragraphe de développement {$developpement->ordre} — titre",
                'Le titre doit avoir au moins 3 mots.',
            );
            $manquants = $this->ajouterSiInsuffisant(
                $manquants,
                (string) $developpement->contenu,
                50,
                "Paragraphe de développement {$developpement->ordre} — contenu",
                'Le contenu doit avoir au moins 50 mots.',
            );
        }

        return $manquants;
    }

    /**
     * Compte les mots d'un contenu texte ou HTML après normalisation.
     */
    private function nombreMots(?string $contenu): int
    {
        $texte = trim(html_entity_decode(strip_tags((string) $contenu)));

        if ($texte === '') {
            return 0;
        }

        return count(preg_split('/\s+/u', $texte, -1, PREG_SPLIT_NO_EMPTY));
    }
}
