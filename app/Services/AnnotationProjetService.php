<?php

namespace App\Services;

use App\Actions\SupprimerAnnotationsOrphelines;
use App\Exceptions\TransactionEchoueeException;
use App\Helpers\HtmlHelper;
use App\Models\ProjetAnnotation;
use App\Models\ProjetDeveloppement;
use App\Models\ProjetRecherche;
use App\Models\ProjetRenvoi;
use App\Models\ProjetSectionContenu;
use App\Models\ProjetSectionParagraphe;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Service des annotations inline de l'enseignant dans le contenu d'un projet.
 */
class AnnotationProjetService
{
    /**
     * Crée le service.
     */
    public function __construct(private SupprimerAnnotationsOrphelines $supprimerOrphelines) {}

    /**
     * Enregistre le HTML annoté du champ et crée ou met à jour l'annotation correspondante.
     *
     * @param  array{champ: string, html: string, commentaire_id: string, contenu: string, annotation_type: string, points_malus?: float|int|string|null, cible_user_id?: int|null}  $donnees
     *
     * @throws ModelNotFoundException si le champ vise une ressource d'un autre projet
     * @throws TransactionEchoueeException
     */
    public function enregistrer(
        ProjetRecherche $projet, array $donnees,
        User $auteur): ProjetAnnotation
    {
        // points_malus et cible_user_id n'ont de sens que pour une correction
        $estCorrection = $donnees['annotation_type'] === 'correction';

        try {
            $annotation = DB::transaction(function () use ($projet, $donnees, $auteur, $estCorrection) {
                $this->mettreAJourChampHtml($projet, $donnees['champ'], $donnees['html']);
                $this->supprimerOrphelines->execute($projet, $donnees['champ'], $donnees['html']);

                return ProjetAnnotation::updateOrCreate(
                    ['projet_id' => $projet->id, 'commentaire_id' => $donnees['commentaire_id']],
                    [
                        'champ' => $donnees['champ'],
                        'contenu' => $donnees['contenu'],
                        'position' => HtmlHelper::positionAnnotation($donnees['html'], $donnees['commentaire_id']),
                        'mot_annote' => HtmlHelper::motAnnote($donnees['html'], $donnees['commentaire_id']),
                        'annotation_type' => $donnees['annotation_type'],
                        'points_malus' => $estCorrection && isset($donnees['points_malus'])
                            ? (float) $donnees['points_malus']
                            : null,
                        'cible_user_id' => $estCorrection ? ($donnees['cible_user_id'] ?? null) : null,
                        'user_id' => $auteur->id,
                    ]
                );
            });
        } catch (QueryException $e) {
            report($e);

            throw TransactionEchoueeException::depuis($e);
        }

        return $annotation;
    }

    /**
     * Supprime l'annotation et enregistre le HTML du champ, d'où la marque a été retirée.
     *
     * @throws ModelNotFoundException si le champ vise une ressource d'un autre projet
     * @throws TransactionEchoueeException
     */
    public function supprimer(ProjetRecherche $projet, ProjetAnnotation $annotation,
        string $champ, string $html): void
    {
        try {
            DB::transaction(function () use ($projet, $annotation, $champ, $html) {
                $this->mettreAJourChampHtml($projet, $champ, $html);
                $annotation->delete();
            });
        } catch (QueryException $e) {
            report($e);

            throw TransactionEchoueeException::depuis($e);
        }
    }

    /**
     * Met à jour le contenu HTML d'un champ annotable.
     *
     * Champs fixes : `page_titre_contenu`, `table_matieres_contenu`.
     * Préfixes : `developpement_`, `section_paragraphe_`, `section_`, `renvoi_`.
     * Toute autre valeur est rejetée en amont par UpsertProjetAnnotationRequest::CHAMP_ANNOTABLE_REGEX.
     *
     * @throws ModelNotFoundException si la ressource n'appartient pas au projet
     */
    private function mettreAJourChampHtml(ProjetRecherche $projet, string $champ, string $html): void
    {
        if ($champ === 'page_titre_contenu' || $champ === 'table_matieres_contenu') {
            $projet->update([$champ => $html]);
        } elseif (str_starts_with($champ, 'developpement_')) {
            ProjetDeveloppement::where('id', $this->idDuChamp($champ, 'developpement_'))
                ->where('projet_id', $projet->id)
                ->firstOrFail()
                ->update(['contenu' => $html]);
        } elseif (str_starts_with($champ, 'section_paragraphe_')) {
            // Tester avant `section_`, qui est un préfixe de `section_paragraphe_`
            ProjetSectionParagraphe::where('id', $this->idDuChamp($champ, 'section_paragraphe_'))
                ->where('projet_id', $projet->id)
                ->firstOrFail()
                ->update(['contenu' => $html]);
        } elseif (str_starts_with($champ, 'section_')) {
            ProjetSectionContenu::updateOrCreate(
                ['projet_id' => $projet->id, 'section_id' => $this->idDuChamp($champ, 'section_')],
                ['contenu' => $html],
            );
        } elseif (str_starts_with($champ, 'renvoi_')) {
            ProjetRenvoi::where('id', $this->idDuChamp($champ, 'renvoi_'))
                ->where('projet_id', $projet->id)
                ->firstOrFail()
                ->update(['contenu' => $html]);
        }
    }

    /**
     * Extrait l'identifiant numérique qui suit le préfixe d'un nom de champ (ex. « renvoi_12 » → 12).
     */
    private function idDuChamp(string $champ, string $prefixe): int
    {
        return (int) mb_substr($champ, mb_strlen($prefixe));
    }
}
