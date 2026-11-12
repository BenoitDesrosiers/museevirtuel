<?php

namespace App\Actions;

use App\Exceptions\TransactionEchoueeException;
use App\Models\ProjetQuestionChoisie;
use App\Models\ProjetRecherche;
use App\Models\TypeProjetSection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Action qui remplace les questions choisies par un groupe dans une section « choix de questions ».
 */
class ChoisirQuestions
{
    /**
     * Remplace les choix existants du projet pour la section par la liste fournie.
     *
     * @param  list<int>  $questionIds  Questions de la banque retenues ; vide pour tout désélectionner
     *
     * @throws TransactionEchoueeException
     */
    public function execute(ProjetRecherche $projet, TypeProjetSection $section, array $questionIds): void
    {
        try {
            DB::transaction(function () use ($projet, $section, $questionIds) {
                ProjetQuestionChoisie::where('projet_id', $projet->id)
                    ->where('section_id', $section->id)
                    ->delete();

                foreach ($questionIds as $questionId) {
                    ProjetQuestionChoisie::create([
                        'projet_id' => $projet->id,
                        'section_id' => $section->id,
                        'question_banque_id' => $questionId,
                    ]);
                }
            });
        } catch (QueryException $e) {
            report($e);

            throw TransactionEchoueeException::depuis($e);
        }
    }
}
