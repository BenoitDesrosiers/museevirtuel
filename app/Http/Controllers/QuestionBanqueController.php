<?php

namespace App\Http\Controllers;

use App\Actions\ChoisirQuestions;
use App\Exceptions\TransactionEchoueeException;
use App\Http\Requests\QuestionBanque\ChoisirQuestionBanqueRequest;
use App\Http\Requests\QuestionBanque\ReorderQuestionBanqueRequest;
use App\Http\Requests\QuestionBanque\SaveQuestionBanqueRequest;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetRecherche;
use App\Models\QuestionBanque;
use App\Models\TypeProjet;
use App\Models\TypeProjetSection;
use App\Services\OrdreElements;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;

/**
 * Banque de questions d'une section « choix de questions » et choix du groupe parmi elles.
 */
class QuestionBanqueController extends Controller
{
    /**
     * Ajoute une question dans la banque d'une section de type 'choix_questions'.
     *
     * Accessible aux enseignants et admins uniquement (vérifié via authorize sur le cours).
     * Les données sont validées par SaveQuestionBanqueRequest.
     */
    public function store(
        SaveQuestionBanqueRequest $request,
        Cours $cours,
        TypeProjet $typeProjet,
        TypeProjetSection $section,
        OrdreElements $ordres,
    ): RedirectResponse {
        abort_if($section->type !== 'choix_questions', 422);

        $data = $request->validated();

        $ordre = $ordres->prochain($section->questionsBanque());

        $section->questionsBanque()->create([
            'contenu' => $data['contenu'],
            'ordre' => $ordre,
        ]);

        return back()->with('success', __('questions.added'));
    }

    /**
     * Met à jour le contenu d'une question de la banque.
     *
     * Les données sont validées par SaveQuestionBanqueRequest.
     */
    public function update(
        SaveQuestionBanqueRequest $request,
        Cours $cours,
        TypeProjet $typeProjet,
        TypeProjetSection $section,
        QuestionBanque $question,
    ): RedirectResponse {
        $data = $request->validated();

        $question->update(['contenu' => $data['contenu']]);

        return back()->with('success', __('questions.updated'));
    }

    /**
     * Réordonne les questions d'une section.
     *
     * Reçoit un tableau d'IDs dans l'ordre désiré.
     */
    public function reorder(
        ReorderQuestionBanqueRequest $request,
        Cours $cours,
        TypeProjet $typeProjet,
        TypeProjetSection $section,
        OrdreElements $ordres,
    ): RedirectResponse {
        try {
            $ordres->reordonner($section->questionsBanque(), $request->ids());
        } catch (TransactionEchoueeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back();
    }

    /**
     * Supprime une question de la banque.
     *
     * Les ProjetQuestionChoisie associées sont supprimées en cascade (DB).
     *
     * @throws AuthorizationException
     */
    public function destroy(
        Cours $cours,
        TypeProjet $typeProjet,
        TypeProjetSection $section,
        QuestionBanque $question,
        OrdreElements $ordres,
    ): RedirectResponse {
        abort_if($typeProjet->cours_id !== $cours->id, 404);
        $this->authorize('update', $typeProjet);
        abort_if($section->type_projet_id !== $typeProjet->id, 404);
        abort_if($question->section_id !== $section->id, 404);

        try {
            $ordres->supprimer($question, $section->questionsBanque());
        } catch (TransactionEchoueeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('questions.deleted'));
    }

    /**
     * Synchronise les questions choisies par un groupe pour une section de projet.
     *
     * Reçoit `question_ids[]` — remplace les choix existants de ce projet pour cette section.
     * Seuls les membres du groupe peuvent choisir.
     * Les données sont validées par ChoisirQuestionBanqueRequest.
     */
    public function choisir(
        ChoisirQuestionBanqueRequest $request,
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
        TypeProjetSection $section,
        ChoisirQuestions $choisirQuestionsAction,
    ): RedirectResponse {
        abort_if($section->type !== 'choix_questions', 422);

        /** @var ProjetRecherche $projet */
        $projet = ProjetRecherche::where('groupe_id', $groupe->id)
            ->where('type_projet_id', $typeProjet->id)
            ->firstOrFail();

        $validated = $request->validated();

        // Vérifier que toutes les questions appartiennent bien à cette section
        $questionIds = $validated['question_ids'];

        if (! empty($questionIds)) {
            $validCount = QuestionBanque::whereIn('id', $questionIds)
                ->where('section_id', $section->id)
                ->count();

            abort_if($validCount !== count($questionIds), 422);
        }

        try {
            $choisirQuestionsAction->execute($projet, $section, $questionIds);
        } catch (TransactionEchoueeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('questions.choices_saved'));
    }
}
