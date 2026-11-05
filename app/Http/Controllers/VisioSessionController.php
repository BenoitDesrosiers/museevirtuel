<?php

namespace App\Http\Controllers;

use App\Models\Cours;
use App\Models\VisioConference;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Démarrage et fin d'une session de visioconférence.
 *
 * Accessible à l'enseignant du cours et aux étudiants concernés par la visio,
 * car un étudiant peut aussi initier la rencontre (VisioConferencePolicy::participer).
 */
class VisioSessionController extends Controller
{
    /**
     * Démarre une visioconférence en enregistrant l'heure de début.
     *
     * @throws HttpException si la visio n'appartient pas au cours ou est déjà démarrée
     * @throws AuthorizationException
     */
    public function demarrer(Cours $cours, VisioConference $visio): RedirectResponse
    {
        abort_if($visio->cours_id !== $cours->id, 404);
        $this->authorize('participer', $visio);
        abort_if($visio->started_at !== null, 422);

        $visio->update(['started_at' => now()]);

        return back()->with('success', __('visio.started'));
    }

    /**
     * Termine une visioconférence en enregistrant l'heure de fin.
     *
     * @throws HttpException si la visio n'appartient pas au cours ou est déjà terminée
     * @throws AuthorizationException
     */
    public function terminer(Cours $cours, VisioConference $visio): RedirectResponse
    {
        abort_if($visio->cours_id !== $cours->id, 404);
        $this->authorize('participer', $visio);
        abort_if($visio->ended_at !== null, 422);

        $visio->update(['ended_at' => now()]);

        return back()->with('success', __('visio.ended'));
    }
}
