<?php

namespace App\Http\Controllers\Groupe;

use App\Concerns\ValidatesGroupeChain;
use App\Exceptions\TransactionEchoueeException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Groupe\DestroyGroupeNoteCorrectionRequest;
use App\Http\Requests\Groupe\UpsertGroupeNoteCorrectionRequest;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\GroupeNote;
use App\Models\GroupeNoteCorrection;
use App\Services\CorrectionNoteService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
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
    public function upsert(
        UpsertGroupeNoteCorrectionRequest $request, Cours $cours,
        Classe $classe, Groupe $groupe, GroupeNote $note,
        CorrectionNoteService $correctionNoteService): RedirectResponse
    {
        $this->verifierChaine($cours, $classe, $groupe, $note);
        $this->authorize('corriger', $note);

        try {
            $correctionNoteService->enregistrer($note, $request->validated(), $request->user());
        } catch (TransactionEchoueeException $e) {
            return back()->with('error', $e->getMessage());
        }

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
    public function destroy(
        DestroyGroupeNoteCorrectionRequest $request, Cours $cours, Classe $classe,
        Groupe $groupe, GroupeNote $note, GroupeNoteCorrection $correction,
        CorrectionNoteService $correctionNoteService): RedirectResponse
    {
        $this->verifierChaine($cours, $classe, $groupe, $note);
        abort_if($correction->note_id !== $note->id, 404);
        $this->authorize('corriger', $note);

        try {
            $correctionNoteService->supprimer($note, $correction, $request->validated('note_html'));
        } catch (TransactionEchoueeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('groupe.note_correction_deleted'));
    }
}
