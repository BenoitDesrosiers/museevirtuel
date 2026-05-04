<?php

namespace App\Http\Controllers;

use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetQuestionChoisie;
use App\Models\ProjetRecherche;
use App\Models\QuestionBanque;
use App\Models\TypeProjet;
use App\Models\TypeProjetSection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class QuestionBanqueController extends Controller
{
    /**
     * Ajoute une question dans la banque d'une section de type 'choix_questions'.
     *
     * Accessible aux enseignants et admins uniquement (vérifié via authorize sur le cours).
     */
    public function store(
        Request $request,
        Cours $cours,
        TypeProjet $typeProjet,
        TypeProjetSection $section,
    ): RedirectResponse {
        $this->authorize('update', $cours);
        abort_if($typeProjet->cours_id !== $cours->id, 404);
        abort_if($section->type_projet_id !== $typeProjet->id, 404);
        abort_if($section->type !== 'choix_questions', 422);

        $data = $request->validate([
            'contenu' => ['required', 'string', 'max:2000'],
        ]);

        $ordre = ($section->questionsBanque()->max('ordre') ?? 0) + 1;

        $section->questionsBanque()->create([
            'contenu' => $data['contenu'],
            'ordre' => $ordre,
        ]);

        return back()->with('success', __('questions.added'));
    }

    /**
     * Met à jour le contenu d'une question de la banque.
     */
    public function update(
        Request $request,
        Cours $cours,
        TypeProjet $typeProjet,
        TypeProjetSection $section,
        QuestionBanque $question,
    ): RedirectResponse {
        $this->authorize('update', $cours);
        abort_if($typeProjet->cours_id !== $cours->id, 404);
        abort_if($section->type_projet_id !== $typeProjet->id, 404);
        abort_if($question->section_id !== $section->id, 404);

        $data = $request->validate([
            'contenu' => ['required', 'string', 'max:2000'],
        ]);

        $question->update(['contenu' => $data['contenu']]);

        return back()->with('success', __('questions.updated'));
    }

    /**
     * Réordonne les questions d'une section.
     *
     * Reçoit un tableau d'IDs dans l'ordre désiré.
     */
    public function reorder(
        Request $request,
        Cours $cours,
        TypeProjet $typeProjet,
        TypeProjetSection $section,
    ): RedirectResponse {
        $this->authorize('update', $cours);
        abort_if($typeProjet->cours_id !== $cours->id, 404);
        abort_if($section->type_projet_id !== $typeProjet->id, 404);

        $validated = $request->validate([
            'ordre' => ['required', 'array'],
            'ordre.*' => ['required', 'integer'],
        ]);

        foreach ($validated['ordre'] as $index => $questionId) {
            QuestionBanque::where('id', $questionId)
                ->where('section_id', $section->id)
                ->update(['ordre' => $index + 1]);
        }

        return back();
    }

    /**
     * Supprime une question de la banque.
     *
     * Les ProjetQuestionChoisie associées sont supprimées en cascade (DB).
     */
    public function destroy(
        Cours $cours,
        TypeProjet $typeProjet,
        TypeProjetSection $section,
        QuestionBanque $question,
    ): RedirectResponse {
        $this->authorize('update', $cours);
        abort_if($typeProjet->cours_id !== $cours->id, 404);
        abort_if($section->type_projet_id !== $typeProjet->id, 404);
        abort_if($question->section_id !== $section->id, 404);

        $question->delete();

        // Renuméroter les questions restantes
        $section->questionsBanque()->orderBy('ordre')->each(
            function (QuestionBanque $q, int $index): void {
                $q->update(['ordre' => $index + 1]);
            }
        );

        return back()->with('success', __('questions.deleted'));
    }

    /**
     * Synchronise les questions choisies par un groupe pour une section de projet.
     *
     * Reçoit `question_ids[]` — remplace les choix existants de ce projet pour cette section.
     * Seuls les membres du groupe peuvent choisir.
     */
    public function choisir(
        Request $request,
        Cours $cours,
        Classe $classe,
        Groupe $groupe,
        TypeProjet $typeProjet,
        TypeProjetSection $section,
    ): RedirectResponse {
        abort_if($classe->cours_id !== $cours->id, 404);
        abort_if($groupe->classe_id !== $classe->id, 404);
        abort_if($section->type_projet_id !== $typeProjet->id, 404);
        abort_if($section->type !== 'choix_questions', 422);

        // Seuls les membres du groupe peuvent choisir des questions
        abort_unless(
            $groupe->membres()->where('user_id', auth()->id())->exists(),
            403
        );

        /** @var ProjetRecherche $projet */
        $projet = ProjetRecherche::where('groupe_id', $groupe->id)
            ->where('type_projet_id', $typeProjet->id)
            ->firstOrFail();

        $validated = $request->validate([
            'question_ids' => ['present', 'array'],
            'question_ids.*' => ['integer', 'exists:question_banques,id'],
        ]);

        // Vérifier que toutes les questions appartiennent bien à cette section
        $questionIds = $validated['question_ids'];

        if (! empty($questionIds)) {
            $validCount = QuestionBanque::whereIn('id', $questionIds)
                ->where('section_id', $section->id)
                ->count();

            abort_if($validCount !== count($questionIds), 422);
        }

        // Supprimer les anciens choix pour cette section + projet
        ProjetQuestionChoisie::where('projet_id', $projet->id)
            ->where('section_id', $section->id)
            ->delete();

        // Insérer les nouveaux choix
        foreach ($questionIds as $questionId) {
            ProjetQuestionChoisie::create([
                'projet_id' => $projet->id,
                'section_id' => $section->id,
                'question_banque_id' => $questionId,
            ]);
        }

        return back()->with('success', __('questions.choices_saved'));
    }
}
