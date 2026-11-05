<?php

namespace App\Http\Controllers\Groupe;

use App\Concerns\ValidatesGroupeChain;
use App\Http\Controllers\Controller;
use App\Http\Requests\Groupe\DestroyGroupeNoteCorrectionRequest;
use App\Http\Requests\Groupe\UpsertGroupeNoteCorrectionRequest;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\GroupeNote;
use App\Models\GroupeNoteCorrection;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Contrôleur de gestion des corrections inline de l'enseignant sur les notes d'un groupe.
 */
class GroupeNoteCorrectionController extends Controller
{
    use ValidatesGroupeChain;

    /**
     * Crée ou met à jour une correction inline sur une note (enseignant uniquement).
     *
     * Données validées par UpsertGroupeNoteCorrectionRequest.
     *
     * @throws HttpException si la note n'appartient pas au groupe
     * @throws AuthorizationException si l'utilisateur n'est pas l'enseignant du cours
     */
    public function upsert(UpsertGroupeNoteCorrectionRequest $request, Cours $cours, Classe $classe, Groupe $groupe, GroupeNote $note): RedirectResponse
    {
        $this->verifierChaine($cours, $classe, $groupe, $note);
        $this->authorize('corriger', $note);

        $validated = $request->validated();

        DB::transaction(function () use ($note, $validated) {
            $note->update(['contenu' => $validated['note_html']]);

            GroupeNoteCorrection::updateOrCreate(
                ['note_id' => $note->id, 'commentaire_id' => $validated['commentaire_id']],
                ['contenu' => $validated['contenu'], 'user_id' => auth()->id()]
            );
        });

        return back()->with('success', __('groupe.note_correction_saved'));
    }

    /**
     * Supprime une correction inline et met à jour le HTML de la note.
     *
     * Données validées par DestroyGroupeNoteCorrectionRequest.
     *
     * @throws HttpException si la note ou la correction ne correspond pas à l'URL
     * @throws AuthorizationException si l'utilisateur n'est pas l'enseignant du cours
     */
    public function destroy(DestroyGroupeNoteCorrectionRequest $request, Cours $cours, Classe $classe, Groupe $groupe,
        GroupeNote $note, GroupeNoteCorrection $correction): RedirectResponse
    {
        $this->verifierChaine($cours, $classe, $groupe, $note);
        abort_if($correction->note_id !== $note->id, 404);
        $this->authorize('corriger', $note);

        $validated = $request->validated();

        DB::transaction(function () use ($note, $validated, $correction) {
            $note->update(['contenu' => $validated['note_html']]);
            $correction->delete();
        });

        return back()->with('success', __('groupe.note_correction_deleted'));
    }
}
