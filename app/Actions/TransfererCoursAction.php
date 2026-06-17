<?php

namespace App\Actions;

use App\Models\Cours;
use App\Models\TypeProjet;
use App\Models\TypeProjetSection;
use App\Models\TypeProjetTache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransfererCoursAction
{
    /**
     * Duplique un cours existant vers une nouvelle session/année.
     *
     * Copie l'échéancier, les objectifs, les documents (fichiers physiques),
     * les liens d'entrevue et les types de projets (sections, tâches).
     * Les classes, étudiants, groupes et projets ne sont PAS copiés.
     *
     * @param  Cours  $source  Le cours source à transférer.
     * @param  int  $annee  L'année académique du nouveau cours (ex. 2027).
     * @param  string  $session  La session du nouveau cours (hiver, ete, automne).
     */
    public function execute(Cours $source, int $annee, string $session): Cours
    {
        return DB::transaction(function () use ($source, $annee, $session) {
            // Rafraîchir depuis la DB pour récupérer les valeurs par défaut
            // que le modèle en mémoire n'aurait pas si elles n'étaient pas passées au create().
            $source->refresh();

            $nouveau = Cours::create([
                'nom_cours' => $source->nom_cours,
                'description' => $source->description,
                'code' => $source->code,
                'groupe' => $source->groupe,
                'annee' => $annee,
                'session' => $session,
                'is_verrouille' => false,
                'enseignant_id' => $source->enseignant_id,
                'type_cours' => $source->type_cours,
                'taille_equipe_min' => $source->taille_equipe_min,
                'taille_equipe_max' => $source->taille_equipe_max,
            ]);

            $this->copierEcheancier($source, $nouveau);
            $this->copierObjectifs($source, $nouveau);
            $this->copierDocuments($source, $nouveau);
            $this->copierLiensEntrevue($source, $nouveau);
            $this->copierTypesProjets($source, $nouveau);

            return $nouveau;
        });
    }

    /**
     * Copie les étapes de l'échéancier en réinitialisant leur statut d'avancement.
     */
    private function copierEcheancier(Cours $source, Cours $nouveau): void
    {
        foreach ($source->echeancierEtapes as $etape) {
            $nouveau->echeancierEtapes()->create([
                'semaine' => $etape->semaine,
                'periode' => $etape->periode,
                'etape' => $etape->etape,
                'is_done' => false,
                'ordre' => $etape->ordre,
            ]);
        }
    }

    /**
     * Copie les objectifs pédagogiques du cours.
     */
    private function copierObjectifs(Cours $source, Cours $nouveau): void
    {
        foreach ($source->objectifs as $objectif) {
            $nouveau->objectifs()->create([
                'contenu' => $objectif->contenu,
                'ordre' => $objectif->ordre,
            ]);
        }
    }

    /**
     * Copie les documents en dupliquant les fichiers physiques sur le disque.
     *
     * Si un fichier source est manquant, l'entrée est ignorée silencieusement.
     */
    private function copierDocuments(Cours $source, Cours $nouveau): void
    {
        foreach ($source->documents as $document) {
            $sourcePath = public_path($document->file_path);

            if (! file_exists($sourcePath)) {
                continue;
            }

            $ext = pathinfo($document->file_path, PATHINFO_EXTENSION);
            $nouveauNomFichier = Str::uuid().'.'.$ext;
            $nouveauRepertoire = "images/cours/{$nouveau->id}";
            $fullDir = public_path($nouveauRepertoire);

            if (! is_dir($fullDir)) {
                mkdir($fullDir, 0755, true);
            }

            copy($sourcePath, "{$fullDir}/{$nouveauNomFichier}");

            $nouveau->documents()->create([
                'enseignant_id' => $document->enseignant_id,
                'nom_original' => $document->nom_original,
                'file_path' => "{$nouveauRepertoire}/{$nouveauNomFichier}",
                'type' => $document->type,
                'taille' => $document->taille,
            ]);
        }
    }

    /**
     * Copie les liens d'entrevue partagés par l'enseignant.
     */
    private function copierLiensEntrevue(Cours $source, Cours $nouveau): void
    {
        foreach ($source->liensEntrevue as $lien) {
            $nouveau->liensEntrevue()->create([
                'label' => $lien->label,
                'url' => $lien->url,
                'ordre' => $lien->ordre,
            ]);
        }
    }

    /**
     * Copie tous les types de projets avec leurs sections et tâches.
     */
    private function copierTypesProjets(Cours $source, Cours $nouveau): void
    {
        $typesProjets = $source->typesProjets()
            ->with(['sections', 'taches'])
            ->get();

        foreach ($typesProjets as $typeProjet) {
            $this->copierTypeProjet($typeProjet, $nouveau);
        }
    }

    /**
     * Copie un type de projet et toutes ses données associées vers le nouveau cours.
     *
     * La date de remise est réinitialisée et l'accessibilité désactivée,
     * car ces paramètres doivent être reconfigurés pour la nouvelle session.
     */
    private function copierTypeProjet(TypeProjet $typeProjet, Cours $nouveau): void
    {
        $nouveauType = TypeProjet::create([
            'enseignant_id' => $typeProjet->enseignant_id,
            'cours_id' => $nouveau->id,
            'nom' => $typeProjet->nom,
            'description' => $typeProjet->description,
            'accessible' => false,
            'date_remise' => null,
            'remises_multiples' => $typeProjet->remises_multiples,
            'retard_permis' => $typeProjet->retard_permis,
            'generer_page_titre' => $typeProjet->generer_page_titre,
            'generer_table_matieres' => $typeProjet->generer_table_matieres,
            'ponderation' => $typeProjet->ponderation,
            'is_sommatif' => $typeProjet->is_sommatif,
        ]);

        foreach ($typeProjet->sections as $section) {
            TypeProjetSection::create([
                'type_projet_id' => $nouveauType->id,
                'label' => $section->label,
                'description' => $section->description,
                'ordre' => $section->ordre,
                'type' => $section->type,
            ]);
        }

        foreach ($typeProjet->taches as $tache) {
            TypeProjetTache::create([
                'type_projet_id' => $nouveauType->id,
                'titre' => $tache->titre,
                'description' => $tache->description,
                'ordre' => $tache->ordre,
            ]);
        }
    }
}
