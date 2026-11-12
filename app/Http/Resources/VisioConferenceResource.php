<?php

namespace App\Http\Resources;

use App\Models\VisioConference;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Props Inertia d'une visioconférence, communes aux pages cours, groupe et tableaux de bord.
 *
 * @mixin VisioConference
 */
class VisioConferenceResource extends JsonResource
{
    /**
     * Transforme la visioconférence en tableau de props.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cours_id' => $this->cours_id,
            'groupe_id' => $this->groupe_id,
            'jitsi_room' => $this->jitsi_room,
            'titre' => $this->titre,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'recording_url' => $this->recording_url,
            // Fichier local : lu via la route authentifiée ; sinon lien externe tel quel
            'recording_stream_url' => $this->recording_path
                ? route('cours.visio.recording', [$this->cours_id, $this->id])
                : $this->recording_url,
            'has_recording' => (bool) ($this->recording_path || $this->recording_url),
            // Permet au front d'afficher un <video> plutôt qu'un lien
            'recording_is_local' => (bool) $this->recording_path,
            'cours' => $this->whenLoaded('cours', fn () => $this->cours->only('id', 'nom_cours', 'code', 'groupe')),
            'groupe_numero' => $this->whenLoaded('groupe', fn () => $this->groupe?->numero),
            'animateur' => $this->whenLoaded('animateur', fn () => $this->animateur->only('id', 'prenom', 'nom')),
        ];
    }
}
