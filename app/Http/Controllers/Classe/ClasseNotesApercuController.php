<?php

namespace App\Http\Controllers\Classe;

use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\TypeProjet;
use App\Services\CalculNotesService;
use App\Services\NotesAccumuleesClasse;
use Illuminate\Auth\Access\AuthorizationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Aperçus des notes d'une classe (par type de projet et cumulées).
 */
class ClasseNotesApercuController extends Controller
{
    /**
     * Affiche la page d'aperçu des notes de toute la classe pour un TypeProjet donné.
     *
     * Agrège les notes de tous les groupes de la classe : une ligne par étudiant
     * avec son numéro DA et sa note finale (format "DA NOTE").
     * Accessible aux enseignants et admins uniquement.
     *
     * @throws AuthorizationException
     * @throws HttpException
     */
    public function show(Cours $cours, Classe $classe, TypeProjet $typeProjet,
        CalculNotesService $notes): Response
    {
        abort_if($classe->cours_id !== $cours->id, 404);
        $this->authorize('voirNotes', $classe);

        $typeProjet->load('criteres');

        $classe->load([
            'groupes.membres',
            'groupes.projets' => fn ($q) => $q->where('type_projet_id', $typeProjet->id),
            'groupes.projets.critereCorrections',
            'groupes.projets.annotations',
        ]);

        $lignes = collect();

        foreach ($classe->groupes as $groupe) {
            $projet = $groupe->projets->first();

            foreach ($groupe->membres as $membre) {
                $note = $projet !== null
                    ? $notes->pourcentage($typeProjet->criteres,
                        $projet->critereCorrections, $projet->annotations, $membre->id) : null;

                $lignes->push([
                    'da' => preg_replace('/\D/', '', (string) $membre->no_da),
                    'nom' => $membre->nom,
                    'prenom' => $membre->prenom,
                    'note' => $note,
                ]);
            }
        }

        return Inertia::render('Classes/ApercuNotes', [
            'cours' => $cours->only('id', 'nom_cours'),
            'classe' => ['id' => $classe->id, 'numero' => $classe->numero, 'nom' => $classe->nom],
            'typeProjet' => ['id' => $typeProjet->id, 'nom' => $typeProjet->nom],
            'lignes' => $lignes->values(),
        ]);
    }

    /**
     * Affiche la page d'aperçu des notes accumulées (pondérées) de toute la classe.
     * Accessible aux enseignants et admins uniquement.
     *
     * @throws AuthorizationException
     * @throws HttpException
     */
    public function accumulees(Cours $cours, Classe $classe,
        NotesAccumuleesClasse $notesAccumuleesClasse): Response
    {
        abort_if($classe->cours_id !== $cours->id, 404);
        $this->authorize('voirNotes', $classe);

        return Inertia::render('Classes/ApercuNotesAccumulees',
            $notesAccumuleesClasse->pourClasse($cours, $classe));
    }
}
