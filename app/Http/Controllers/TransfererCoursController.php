<?php

namespace App\Http\Controllers;

use App\Actions\TransfererCoursAction;
use App\Http\Requests\TransfererCoursRequest;
use App\Models\Cours;
use Illuminate\Http\RedirectResponse;

class TransfererCoursController extends Controller
{
    /**
     * Transfère un cours vers une nouvelle session/année.
     *
     * Crée une copie complète du cours (échéancier, objectifs, documents,
     * liens d'entrevue, types de projets avec grilles) sans les classes ni les étudiants,
     * puis redirige vers le nouveau cours créé.
     */
    public function __invoke(
        TransfererCoursRequest $request,
        Cours $cours,
        TransfererCoursAction $action,
    ): RedirectResponse {
        $nouveauCours = $action->execute(
            source: $cours,
            annee: $request->integer('annee'),
            session: $request->string('session'),
        );

        return to_route('cours.show', $nouveauCours)
            ->with('success', __('cours.transfere'));
    }
}
