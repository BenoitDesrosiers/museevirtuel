<?php

namespace App\Services;

use App\Models\Cours;
use App\Models\CoursObjectif;
use App\Models\CoursReference;
use App\Models\EcheancierEtape;
use App\Models\GabaritCours;
use App\Models\GabaritCoursReference;
use App\Models\GabaritEcheancierEtape;
use App\Models\GabaritTypeProjet;
use App\Models\TypeProjet;
use App\Models\TypeProjetSection;

/**
 * Applique un gabarit de cours à un cours existant.
 *
 * Clone les objectifs pédagogiques, les types de projets (avec leurs sections)
 * et les étapes d'échéancier depuis le gabarit vers le cours cible.
 *
 * Le service ne crée rien si le cours possède déjà des données dans chaque
 * catégorie — il est donc sûr à appeler plusieurs fois.
 */
class AppliquerGabaritService
{
    /**
     * Applique le gabarit identifié par son slug au cours donné.
     *
     * @param  Cours  $cours  Le cours qui recevra les données du gabarit
     * @param  string  $slug  Identifiant stable du gabarit (ex: 'cours_complet')
     */
    public function appliquer(Cours $cours, string $slug): void
    {
        $gabarit = GabaritCours::where('slug', $slug)
            ->with(['objectifs', 'typesProjets.sections', 'echeancierEtapes', 'references'])
            ->first();

        // Le gabarit n'existe pas encore — rien à faire
        if (! $gabarit) {
            return;
        }

        $this->cloneObjectifs($cours, $gabarit);
        $this->cloneTypesProjets($cours, $gabarit);
        $this->cloneEcheancier($cours, $gabarit);
        $this->cloneReferences($cours, $gabarit);
    }

    /**
     * Clone les objectifs pédagogiques du gabarit vers le cours.
     *
     * Ignoré si le cours possède déjà au moins un objectif.
     */
    private function cloneObjectifs(Cours $cours, GabaritCours $gabarit): void
    {
        if ($cours->objectifs()->exists()) {
            return;
        }

        $inserts = $gabarit->objectifs->map(fn ($o) => [
            'cours_id' => $cours->id,
            'contenu' => $o->contenu,
            'ordre' => $o->ordre,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all();

        if ($inserts) {
            CoursObjectif::insert($inserts);
        }
    }

    /**
     * Clone les types de projets (avec leurs sections) du gabarit vers le cours.
     *
     * Chaque TypeProjet est rattaché à l'enseignant du cours.
     * Ignoré si le cours possède déjà au moins un type de projet.
     */
    private function cloneTypesProjets(Cours $cours, GabaritCours $gabarit): void
    {
        if (TypeProjet::where('cours_id', $cours->id)->exists()) {
            return;
        }

        $gabarit->typesProjets->each(function (GabaritTypeProjet $gtp) use ($cours): void {
            /** @var TypeProjet $typeProjet */
            $typeProjet = TypeProjet::create([
                'enseignant_id' => $cours->enseignant_id,
                'cours_id' => $cours->id,
                'nom' => $gtp->nom,
                'description' => $gtp->description,
                'ponderation' => $gtp->ponderation,
                'is_sommatif' => $gtp->is_sommatif,
                'generer_page_titre' => $gtp->generer_page_titre,
                'generer_table_matieres' => $gtp->generer_table_matieres,
                'aide_reference' => $gtp->aide_reference,
                'accessible' => false,
                'remises_multiples' => false,
                'retard_permis' => false,
            ]);

            $inserts = $gtp->sections->map(fn ($s) => [
                'type_projet_id' => $typeProjet->id,
                'label' => $s->label,
                'type' => $s->type,
                'ordre' => $s->ordre,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();

            if ($inserts) {
                TypeProjetSection::insert($inserts);
            }
        });
    }

    /**
     * Clone les étapes d'échéancier du gabarit vers le cours.
     *
     * Ignoré si le cours possède déjà au moins une étape.
     */
    private function cloneEcheancier(Cours $cours, GabaritCours $gabarit): void
    {
        if ($cours->echeancierEtapes()->exists()) {
            return;
        }

        $inserts = $gabarit->echeancierEtapes->map(fn (GabaritEcheancierEtape $e) => [
            'cours_id' => $cours->id,
            'semaine' => $e->semaine,
            'etape' => $e->etape,
            'is_done' => false,
            'ordre' => $e->ordre,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all();

        if ($inserts) {
            EcheancierEtape::insert($inserts);
        }
    }

    /**
     * Clone les références bibliographiques du gabarit vers le cours.
     *
     * Ignoré si le cours possède déjà au moins une référence.
     */
    private function cloneReferences(Cours $cours, GabaritCours $gabarit): void
    {
        if ($cours->references()->exists()) {
            return;
        }

        $inserts = $gabarit->references->map(fn (GabaritCoursReference $r) => [
            'cours_id' => $cours->id,
            'nom' => $r->nom,
            'url' => $r->url,
            'ordre' => $r->ordre,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all();

        if ($inserts) {
            CoursReference::insert($inserts);
        }
    }
}
