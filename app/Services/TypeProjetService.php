<?php

namespace App\Services;

use App\Enums\TypeSection;
use App\Exceptions\TransactionEchoueeException;
use App\Models\Cours;
use App\Models\TypeProjet;
use App\Models\TypeProjetSection;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Création et mise à jour d'un type de projet avec ses sections.
 *
 * Les données reçues sont celles validées par StoreTypeProjetRequest / UpdateTypeProjetRequest.
 */
class TypeProjetService
{
    /**
     * Crée un type de projet (inaccessible aux étudiants par défaut) et ses sections.
     *
     * Un projet musée reçoit en plus son critère de publication et sa section de contenu.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws TransactionEchoueeException
     */
    public function creer(User $enseignant, Cours $cours, array $data): TypeProjet
    {
        try {
            $typeProjet = DB::transaction(function () use ($enseignant, $cours, $data) {
                return $this->creerAvecSections($enseignant, $cours, $data);
            });
        } catch (QueryException $e) {
            report($e);

            throw TransactionEchoueeException::depuis($e);
        }

        return $typeProjet;
    }

    /**
     * Met à jour les paramètres du type de projet et, si `sections` est fourni, synchronise ses sections.
     *
     * Les booléens absents gardent leur valeur actuelle.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws TransactionEchoueeException
     */
    public function mettreAJour(TypeProjet $typeProjet, array $data): void
    {
        try {
            DB::transaction(function () use ($typeProjet, $data) {
                $this->mettreAJourAvecSections($typeProjet, $data);
            });
        } catch (QueryException $e) {
            report($e);

            throw TransactionEchoueeException::depuis($e);
        }
    }

    /**
     * Crée le type de projet, ses sections et, pour un musée, sa structure initiale.
     *
     * Appelée dans la transaction de creer().
     *
     * @param  array<string, mixed>  $data
     */
    private function creerAvecSections(User $enseignant, Cours $cours, array $data): TypeProjet
    {
        $typeProjet = TypeProjet::create([
            'enseignant_id' => $enseignant->id,
            'cours_id' => $cours->id,
            'nom' => $data['nom'],
            'type' => $data['type'] ?? 'standard',
            'description' => $data['description'] ?? null,
            'accessible' => false,
            'date_remise' => $data['date_remise'] ?? null,
            'remises_multiples' => (bool) ($data['remises_multiples'] ?? false),
            'retard_permis' => (bool) ($data['retard_permis'] ?? false),
            'generer_page_titre' => (bool) ($data['generer_page_titre'] ?? true),
            'generer_table_matieres' => (bool) ($data['generer_table_matieres'] ?? true),
            'aide_reference' => (bool) ($data['aide_reference'] ?? false),
            // Nouvelles sections legacy — désactivées par défaut sur les nouveaux types
            'has_introduction' => (bool) ($data['has_introduction'] ?? false),
            'has_conclusion_individuelle' => (bool) ($data['has_conclusion_individuelle'] ?? false),
            'ponderation' => $data['ponderation'] ?? null,
            'is_sommatif' => (bool) ($data['is_sommatif'] ?? true),
        ]);

        foreach ($data['sections'] ?? [] as $index => $section) {
            $typeProjet->sections()->create($this->attributsSection($section, $index));
        }

        if ($typeProjet->isMusee()) {
            $this->initialiserMusee($typeProjet);
        }

        return $typeProjet;
    }

    /**
     * Applique la mise à jour du type de projet et de ses sections.
     *
     * Appelée dans la transaction de mettreAJour().
     *
     * @param  array<string, mixed>  $data
     */
    private function mettreAJourAvecSections(TypeProjet $typeProjet, array $data): void
    {
        $generaitPageTitre = (bool) $typeProjet->generer_page_titre;
        $generePageTitre = (bool) ($data['generer_page_titre'] ?? $typeProjet->generer_page_titre);

        $typeProjet->update([
            'nom' => $data['nom'],
            'description' => $data['description'] ?? null,
            'date_remise' => $data['date_remise'] ?? null,
            'remises_multiples' => (bool) ($data['remises_multiples'] ?? $typeProjet->remises_multiples),
            'retard_permis' => (bool) ($data['retard_permis'] ?? $typeProjet->retard_permis),
            'generer_page_titre' => $generePageTitre,
            'generer_table_matieres' => (bool) ($data['generer_table_matieres'] ?? $typeProjet->generer_table_matieres),
            'aide_reference' => (bool) ($data['aide_reference'] ?? $typeProjet->aide_reference),
            'has_introduction' => (bool) ($data['has_introduction'] ?? $typeProjet->has_introduction),
            'has_conclusion_individuelle' => (bool) ($data['has_conclusion_individuelle'] ?? $typeProjet->has_conclusion_individuelle),
            'ponderation' => $data['ponderation'] ?? $typeProjet->ponderation,
            'is_sommatif' => (bool) ($data['is_sommatif'] ?? $typeProjet->is_sommatif),
        ]);

        // Vider le contenu auto-généré quand le flag passe de auto → manuel
        if ($generaitPageTitre && ! $generePageTitre) {
            $typeProjet->projets()->update(['page_titre_contenu' => null]);
        }

        // Sections absentes de la requête = on n'y touche pas
        if (array_key_exists('sections', $data)) {
            $this->synchroniserSections($typeProjet, $data['sections'] ?? []);
        }
    }

    /**
     * Synchronise complètement les sections : supprime les absentes, met à jour celles avec `id`, crée les autres.
     *
     * La suppression cascade en base sur projet_section_contenus.
     *
     * @param  array<int, array<string, mixed>>  $sections
     */
    private function synchroniserSections(TypeProjet $typeProjet, array $sections): void
    {
        $idsRecus = collect($sections)->pluck('id')->filter()->values()->all();

        $typeProjet->sections()
            ->when($idsRecus !== [], fn ($q) => $q->whereNotIn('id', $idsRecus))
            ->delete();

        foreach ($sections as $index => $section) {
            if (! empty($section['id'])) {
                // Le filtre sur type_projet_id ignore les ids appartenant à un autre type de projet
                TypeProjetSection::where('id', $section['id'])
                    ->where('type_projet_id', $typeProjet->id)
                    ->update($this->attributsSection($section, $index));
            } else {
                $typeProjet->sections()->create($this->attributsSection($section, $index));
            }
        }
    }

    /**
     * Crée le critère de publication (20 pts) et la section de contenu d'un projet musée.
     */
    private function initialiserMusee(TypeProjet $typeProjet): void
    {
        // Le critère sera coché lors de l'approbation de la publication par l'enseignant.
        $typeProjet->criteres()->create([
            'type' => 'positif',
            'contenu_type' => 'texte',
            'contenu' => '<p>Publication du musée dans la galerie.</p>',
            'pointage' => 20,
            'visible' => true,
            'is_musee_publication' => true,
            'ordre' => 1,
        ]);

        // Un musée est un long bloc libre, sans structure intro/développement/conclusion imposée.
        $typeProjet->sections()->create([
            'label' => 'Publication',
            'type' => TypeSection::MuseeContenu->value,
            'ordre' => 1,
        ]);
    }

    /**
     * Attributs d'une section à partir des données reçues et de sa position (ordre 1-based).
     *
     * @param  array<string, mixed>  $section
     * @return array<string, mixed>
     */
    private function attributsSection(array $section, int $index): array
    {
        return [
            'label' => $section['label'],
            'description' => $section['description'] ?? null,
            'type' => $section['type'] ?? TypeSection::Texte->value,
            'ordre' => $index + 1,
        ];
    }
}
