<?php

namespace App\Http\Controllers;

use App\Http\Requests\Visio\StoreVisioConferenceRequest;
use App\Http\Requests\Visio\UpdateVisioConferenceRequest;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\VisioConference;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Visioconférences d'un cours : création, modification et suppression.
 */
class VisioConferenceController extends Controller
{
    /**
     * Crée une nouvelle visioconférence pour le cours.
     *
     * Génère un identifiant de salle Jitsi unique au format : XXXX-XXXXXXXX.
     * Si groupe_id est fourni, la session est ciblée pour ce groupe seulement.
     *
     * Accessible à l'enseignant du cours (avec ou sans groupe_id) et aux membres
     * d'un groupe spécifique lorsque groupe_id est fourni (VisioConferencePolicy::create).
     * Les données sont validées par StoreVisioConferenceRequest.
     *
     * @throws HttpException si le groupe ciblé n'appartient pas au cours
     * @throws AuthorizationException
     */
    public function store(StoreVisioConferenceRequest $request, Cours $cours): RedirectResponse
    {
        $data = $request->validated();
        $groupe = null;

        if (! empty($data['groupe_id'])) {
            // Vérifier que le groupe appartient bien à ce cours
            $groupe = Groupe::whereKey($data['groupe_id'])
                ->whereHas('classe', fn ($q) => $q->where('cours_id', $cours->id))
                ->first();
            abort_if(! $groupe, 422, __('visio.groupe_invalid'));
        }

        $this->authorize('create', [VisioConference::class, $cours, $groupe]);

        $room = str_pad((string) $cours->id, 4, '0', STR_PAD_LEFT).'-'.Str::random(8);

        $cours->visioConferences()->create([
            'animateur_id' => $request->user()->id,
            'groupe_id' => $data['groupe_id'] ?? null,
            'jitsi_room' => $room,
            'titre' => $data['titre'],
            'scheduled_at' => $data['scheduled_at'] ?? null,
        ]);

        return back()->with('success', __('visio.created'));
    }

    /**
     * Met à jour une visioconférence existante (titre, date planifiée, URL enregistrement).
     *
     * Les données sont validées par UpdateVisioConferenceRequest.
     */
    public function update(UpdateVisioConferenceRequest $request, Cours $cours, VisioConference $visio): RedirectResponse
    {
        $data = $request->validated();

        $visio->update($data);

        return back()->with('success', __('visio.updated'));
    }

    /**
     * Supprime une visioconférence.
     *
     * @throws HttpException si la visio n'appartient pas au cours
     * @throws AuthorizationException
     */
    public function destroy(Cours $cours, VisioConference $visio): RedirectResponse
    {
        abort_if($visio->cours_id !== $cours->id, 404);
        $this->authorize('delete', $visio);

        $visio->delete();

        return back()->with('success', __('visio.deleted'));
    }
}
