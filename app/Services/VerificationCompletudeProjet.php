<?php

namespace App\Services;

use App\Models\Groupe;
use App\Models\ProjetConclusion;
use App\Models\ProjetRecherche;
use App\Models\ProjetSectionParagraphe;
use Illuminate\Support\Collection;

/**
 * Vérifie qu'un projet contient les textes requis avant sa remise.
 */
class VerificationCompletudeProjet
{
    /**
     * Retourne les contenus textuels vides ou trop courts avant une remise.
     *
     * Cette validation reprend les mêmes règles que l'éditeur frontend pour
     * empêcher qu'une requête directe contourne les contrôles de l'interface.
     *
     * @return list<array{section: string, raison: string}>
     */
    public function contenusManquants(ProjetRecherche $projet, Groupe $groupe): array
    {
        $projet->loadMissing([
            'typeProjet.sections',
            'sectionContenus',
            'sectionParagraphes',
            'conclusions',
            'developpements',
        ]);

        $sansSections = $projet->typeProjet->sections->isEmpty();

        return [
            ...$this->manquantsSections($projet, $groupe),
            ...($sansSections && $projet->typeProjet->has_introduction ?
                $this->manquantsIntroduction($projet) : []),
            ...($sansSections && $projet->typeProjet->has_conclusion_individuelle
                ? $this->conclusionsInsuffisantes($projet->conclusions->
                    whereNull('section_id'), $groupe, null) : []),
            ...$this->manquantsDeveloppements($projet),
        ];
    }

    /**
     * Vérifie le contenu de chaque section selon son type (texte, paragraphes, individuel).
     *
     * @return list<array{section: string, raison: string}>
     */
    private function manquantsSections(ProjetRecherche $projet, Groupe $groupe): array
    {
        $contenusParSection = $projet->sectionContenus->keyBy('section_id');
        $paragraphesParSection = $projet->sectionParagraphes->groupBy('section_id');
        $conclusionsParSection = $projet->conclusions
            ->whereNotNull('section_id')
            ->groupBy('section_id');

        $manquants = [];

        foreach ($projet->typeProjet->sections as $section) {
            $libelle = $section->label ?: 'Section '.$section->id;

            array_push($manquants, ...match ($section->type) {
                'texte' => $this->manque(
                    (string) $contenusParSection->get($section->id)?->contenu,
                    1,
                    $libelle,
                    'Le contenu textuel est vide.',
                ),
                'paragraphes' => $this->paragraphesVides($paragraphesParSection->get(
                    $section->id, collect()), $libelle),
                'individuel' => $this->conclusionsInsuffisantes($conclusionsParSection->get(
                    $section->id, collect()), $groupe, $libelle),
                default => [],
            });
        }

        return $manquants;
    }

    /**
     * Signale une section de paragraphes sans paragraphe, puis chaque paragraphe vide.
     *
     * @param  Collection<int, ProjetSectionParagraphe>  $paragraphes
     * @return list<array{section: string, raison: string}>
     */
    private function paragraphesVides(Collection $paragraphes, string $libelle): array
    {
        $manquants = $paragraphes->isEmpty()
            ? [['section' => $libelle, 'raison' => 'Aucun paragraphe n’a été ajouté.']]
            : [];

        foreach ($paragraphes as $paragraphe) {
            array_push($manquants, ...$this->manque(
                (string) $paragraphe->contenu,
                1,
                "{$libelle} — paragraphe {$paragraphe->ordre}",
                'Le contenu est vide.',
            ));
        }

        return $manquants;
    }

    /**
     * Vérifie que chaque membre du groupe a une conclusion assez longue.
     *
     * @param  Collection<int, ProjetConclusion>  $conclusions  conclusions de la section (ou générales)
     * @param  string|null  $libelleSection  null pour la conclusion générale hors section
     * @return list<array{section: string, raison: string}>
     */
    private function conclusionsInsuffisantes(Collection $conclusions, Groupe $groupe, ?string $libelleSection): array
    {
        $minimum = config('projet.mots_minimum.conclusion');
        $manquants = [];

        foreach ($groupe->membres as $membre) {
            $nom = "{$membre->prenom} {$membre->nom}";

            array_push($manquants, ...$this->manque(
                (string) $conclusions->firstWhere('user_id', $membre->id)?->contenu,
                $minimum,
                $libelleSection === null ? "Conclusion de {$nom}" : "{$libelleSection} — conclusion de {$nom}",
                "La conclusion doit avoir au moins {$minimum} mots.",
            ));
        }

        return $manquants;
    }

    /**
     * Vérifie les trois parties de l'introduction (sujet amené, posé, divisé).
     *
     * @return list<array{section: string, raison: string}>
     */
    private function manquantsIntroduction(ProjetRecherche $projet): array
    {
        $minimum = config('projet.mots_minimum.introduction');
        $manquants = [];

        foreach ([
            'introduction_amener' => 'Sujet amené',
            'introduction_poser' => 'Sujet posé',
            'introduction_diviser' => 'Sujet divisé',
        ] as $champ => $libelle) {
            array_push($manquants, ...$this->manque(
                (string) $projet->{$champ},
                $minimum,
                $libelle,
                "L'introduction doit avoir au moins {$minimum} mots.",
            ));
        }

        return $manquants;
    }

    /**
     * Vérifie le titre et le contenu de chaque paragraphe de développement.
     *
     * @return list<array{section: string, raison: string}>
     */
    private function manquantsDeveloppements(ProjetRecherche $projet): array
    {
        $seuils = config('projet.mots_minimum');
        $manquants = [];

        foreach ($projet->developpements as $developpement) {
            array_push($manquants,
                ...$this->manque(
                    (string) $developpement->titre,
                    $seuils['developpement_titre'],
                    "Paragraphe de développement {$developpement->ordre} — titre",
                    "Le titre doit avoir au moins {$seuils['developpement_titre']} mots.",
                ),
                ...$this->manque(
                    (string) $developpement->contenu,
                    $seuils['developpement_contenu'],
                    "Paragraphe de développement {$developpement->ordre} — contenu",
                    "Le contenu doit avoir au moins {$seuils['developpement_contenu']} mots.",
                ),
            );
        }

        return $manquants;
    }

    /**
     * Retourne une entrée manquante si le contenu n'atteint pas le nombre de mots minimum,
     * sinon une liste vide.
     *
     * @return list<array{section: string, raison: string}>
     */
    private function manque(string $contenu, int $minimum, string $section, string $raison): array
    {
        return $this->nombreMots($contenu) < $minimum
            ? [['section' => $section, 'raison' => $raison]] : [];
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
