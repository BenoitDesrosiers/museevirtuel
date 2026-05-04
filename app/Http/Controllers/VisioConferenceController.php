<?php

namespace App\Http\Controllers;

use App\Models\Cours;
use App\Models\Groupe;
use App\Models\VisioConference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VisioConferenceController extends Controller
{
    /**
     * Crée une nouvelle visioconférence pour le cours.
     *
     * Génère un identifiant de salle Jitsi unique au format : XXXX-XXXXXXXX.
     * Si groupe_id est fourni, la session est ciblée pour ce groupe seulement.
     */
    public function store(Request $request, Cours $cours): RedirectResponse
    {
        $this->authorize('update', $cours);

        $data = $request->validate([
            'titre' => ['required', 'string', 'max:255'],
            'groupe_id' => ['nullable', 'integer', 'exists:groupes,id'],
            'scheduled_at' => ['nullable', 'date'],
        ]);

        // Vérifier que le groupe_id appartient bien à ce cours
        if (! empty($data['groupe_id'])) {
            $appartient = Groupe::whereKey($data['groupe_id'])
                ->whereHas('classe', fn ($q) => $q->where('cours_id', $cours->id))
                ->exists();
            abort_if(! $appartient, 422, __('visio.groupe_invalid'));
        }

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
     */
    public function update(Request $request, Cours $cours, VisioConference $visio): RedirectResponse
    {
        $this->authorize('update', $cours);
        abort_if($visio->cours_id !== $cours->id, 404);

        $data = $request->validate([
            'titre' => ['sometimes', 'string', 'max:255'],
            'scheduled_at' => ['nullable', 'date'],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date'],
            'recording_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $visio->update($data);

        return back()->with('success', __('visio.updated'));
    }

    /**
     * Supprime une visioconférence.
     */
    public function destroy(Cours $cours, VisioConference $visio): RedirectResponse
    {
        $this->authorize('update', $cours);
        abort_if($visio->cours_id !== $cours->id, 404);

        $visio->delete();

        return back()->with('success', __('visio.deleted'));
    }
}
