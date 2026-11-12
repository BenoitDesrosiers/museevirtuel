<?php

namespace App\Actions;

use App\Helpers\HtmlHelper;
use App\Models\ProjetAnnotation;
use App\Models\ProjetRecherche;

/**
 * Action qui supprime les annotations d'un champ dont la marque a disparu du HTML.
 *
 * Appelée après chaque sauvegarde de contenu : un étudiant qui efface le texte
 * annoté efface aussi la marque, et l'annotation ne doit pas survivre seule.
 */
class SupprimerAnnotationsOrphelines
{
    /**
     * Supprime les annotations du champ absentes du HTML fourni.
     *
     * @param  string  $champ  Clé du champ annoté (ex. « section_12 », « developpement_3 »)
     * @param  string  $html  Contenu HTML sauvegardé, contenant les marques d'annotation
     * @return int Nombre d'annotations supprimées
     */
    public function execute(ProjetRecherche $projet, string $champ, string $html): int
    {
        $idsPresents = HtmlHelper::annotationIds($html);

        return ProjetAnnotation::where('projet_id', $projet->id)
            ->where('champ', $champ)
            ->when(! empty($idsPresents), fn ($q) => $q->whereNotIn('commentaire_id', $idsPresents))
            ->delete();
    }
}
