<?php

namespace App\Http\Controllers\Projet;

use App\Actions\ExportProjetPdf;
use App\Actions\ExportProjetWord;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Projet\Concerns\AutoriseProjetRecherche;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Exports PDF et Word du projet.
 */
class ProjetExportController extends Controller
{
    use AutoriseProjetRecherche;

    /**
     * Génère et retourne le projet en PDF.
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function pdf(Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet): HttpResponse
    {
        $projet = $this->chargerProjetPourExport($cours, $classe, $groupe, $typeProjet);

        return (new ExportProjetPdf)->execute($projet, $groupe);
    }

    /**
     * Génère et retourne le projet en Word (.docx).
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    public function word(Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet): StreamedResponse
    {
        $projet = $this->chargerProjetPourExport($cours, $classe, $groupe, $typeProjet);

        return (new ExportProjetWord)->execute($projet, $groupe);
    }

    /**
     * Autorise l'accès et charge le projet pour les exports PDF et Word.
     *
     * Factorise le guard commun (404/autorisation) et l'eager load partagé
     * par exportPdf et exportWord.
     *
     * @throws HttpException
     * @throws AuthorizationException
     */
    private function chargerProjetPourExport(Cours $cours, Classe $classe, Groupe $groupe, TypeProjet $typeProjet): ProjetRecherche
    {
        abort_if($classe->cours_id !== $cours->id, 404);
        abort_if($groupe->classe_id !== $classe->id, 404);
        $this->verifierTypeProjetAppartientCours($typeProjet, $cours);

        $groupe->load(['membres', 'thematiques', 'classe.cours.enseignant']);
        $this->authorize('view', $groupe);

        $projet = $this->trouverProjet($groupe, $typeProjet);
        $projet->load(['conclusions.etudiant', 'developpements', 'renvois']);

        return $projet;
    }
}
