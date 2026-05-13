<?php

namespace App\Http\Controllers;

use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\VisioConference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class VisioConferenceController extends Controller
{
    /**
     * Crée une nouvelle visioconférence pour le cours.
     *
     * Génère un identifiant de salle Jitsi unique au format : XXXX-XXXXXXXX.
     * Si groupe_id est fourni, la session est ciblée pour ce groupe seulement.
     *
     * Accessible à l'enseignant du cours (avec ou sans groupe_id) et aux membres
     * d'un groupe spécifique lorsque groupe_id est fourni.
     */
    public function store(Request $request, Cours $cours): RedirectResponse
    {
        $user = $request->user();
        $estEnseignant = $cours->enseignant_id === $user->id || $user->isAdmin();

        $data = $request->validate([
            'titre' => ['required', 'string', 'max:255'],
            'groupe_id' => ['nullable', 'integer', 'exists:groupes,id'],
            'scheduled_at' => ['nullable', 'date'],
        ]);

        if (! empty($data['groupe_id'])) {
            // Vérifier que le groupe appartient bien à ce cours
            $groupe = Groupe::whereKey($data['groupe_id'])
                ->whereHas('classe', fn ($q) => $q->where('cours_id', $cours->id))
                ->first();
            abort_if(! $groupe, 422, __('visio.groupe_invalid'));

            // Autoriser l'enseignant du cours ou les membres du groupe ciblé
            $estMembre = $groupe->membres()->where('users.id', $user->id)->exists();
            abort_unless($estEnseignant || $estMembre, 403);
        } else {
            // Sans groupe_id : réservé à l'enseignant
            $this->authorize('update', $cours);
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

    /**
     * Démarre une visioconférence en enregistrant l'heure de début.
     *
     * Accessible à l'enseignant du cours et aux membres de ses groupes,
     * car l'étudiant peut aussi initier la rencontre.
     *
     * @throws HttpException si non autorisé
     */
    public function startSession(Cours $cours, VisioConference $visio): RedirectResponse
    {
        abort_if($visio->cours_id !== $cours->id, 404);
        abort_if($visio->started_at !== null, 422);

        $user = auth()->user();

        $estAutorise = $cours->enseignant_id === $user->id
            || $user->isAdmin()
            || Classe::where('cours_id', $cours->id)
                ->whereHas('groupes', fn ($q) => $q
                    ->whereHas('membres', fn ($q2) => $q2->where('users.id', $user->id))
                )
                ->exists();

        abort_unless($estAutorise, 403);

        $visio->update(['started_at' => now()]);

        return back()->with('success', __('visio.started'));
    }

    /**
     * Termine une visioconférence en enregistrant l'heure de fin.
     *
     * Accessible à l'enseignant du cours et aux membres de ses groupes.
     *
     * @throws HttpException si non autorisé
     */
    public function endSession(Cours $cours, VisioConference $visio): RedirectResponse
    {
        abort_if($visio->cours_id !== $cours->id, 404);
        abort_if($visio->ended_at !== null, 422);

        $user = auth()->user();

        $estAutorise = $cours->enseignant_id === $user->id
            || $user->isAdmin()
            || Classe::where('cours_id', $cours->id)
                ->whereHas('groupes', fn ($q) => $q
                    ->whereHas('membres', fn ($q2) => $q2->where('users.id', $user->id))
                )
                ->exists();

        abort_unless($estAutorise, 403);

        $visio->update(['ended_at' => now()]);

        return back()->with('success', __('visio.ended'));
    }

    /**
     * Enregistre le fichier vidéo d'une rencontre terminée.
     *
     * Remplace l'ancien fichier s'il en existe déjà un.
     * Accessible uniquement à l'enseignant du cours.
     *
     * @throws HttpException si la visio n'appartient pas au cours
     */
    public function storeRecording(Request $request, Cours $cours, VisioConference $visio): RedirectResponse
    {
        $this->authorize('update', $cours);
        abort_if($visio->cours_id !== $cours->id, 404);

        $request->validate([
            'recording' => ['required', 'file', 'mimes:mp4,mov,webm,avi', 'max:1048576'],
        ]);

        // Supprimer l'ancien fichier avant de le remplacer
        if ($visio->recording_path) {
            Storage::delete($visio->recording_path);
        }

        $extension = $request->file('recording')->getClientOriginalExtension();
        $path = $request->file('recording')->storeAs(
            "visio-recordings/{$visio->id}",
            "recording.{$extension}"
        );

        $visio->update(['recording_path' => $path]);

        return back()->with('success', __('visio.recording_saved'));
    }

    /**
     * Diffuse le fichier d'enregistrement en streaming sécurisé.
     *
     * Supporte les range requests pour permettre la navigation dans la vidéo.
     * Accessible à l'enseignant du cours et aux membres de ses groupes.
     *
     * @throws HttpException si non autorisé ou fichier introuvable
     */
    public function streamRecording(Cours $cours, VisioConference $visio): BinaryFileResponse
    {
        abort_if($visio->cours_id !== $cours->id, 404);
        abort_if(! $visio->recording_path, 404);

        $user = auth()->user();

        // Autoriser l'enseignant, les admins, et les membres de n'importe quel groupe du cours
        $estAutorise = $cours->enseignant_id === $user->id
            || $user->isAdmin()
            || Classe::where('cours_id', $cours->id)
                ->whereHas('groupes', fn ($q) => $q
                    ->whereHas('membres', fn ($q2) => $q2->where('users.id', $user->id))
                )
                ->exists();

        abort_unless($estAutorise, 403);

        $filePath = Storage::path($visio->recording_path);
        abort_if(! file_exists($filePath), 404);

        return response()->file($filePath);
    }
}
