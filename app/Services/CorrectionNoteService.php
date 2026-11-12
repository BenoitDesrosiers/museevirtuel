<?php

namespace App\Services;

use App\Exceptions\TransactionEchoueeException;
use App\Models\GroupeNote;
use App\Models\GroupeNoteCorrection;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Service des corrections inline de l'enseignant sur les notes collaboratives d'un groupe.
 */
class CorrectionNoteService
{
    /**
     * Enregistre le HTML annoté de la note et crée ou met à jour la correction.
     *
     * @param  array{note_html: string, commentaire_id: string, contenu: string}  $donnees
     *
     * @throws TransactionEchoueeException
     */
    public function enregistrer(GroupeNote $note, array $donnees, User $auteur): GroupeNoteCorrection
    {
        try {
            $correction = DB::transaction(function () use ($note, $donnees, $auteur) {
                $note->update(['contenu' => $donnees['note_html']]);

                return GroupeNoteCorrection::updateOrCreate(
                    ['note_id' => $note->id, 'commentaire_id' => $donnees['commentaire_id']],
                    ['contenu' => $donnees['contenu'], 'user_id' => $auteur->id]
                );
            });
        } catch (QueryException $e) {
            report($e);

            throw TransactionEchoueeException::depuis($e);
        }

        return $correction;
    }

    /**
     * Supprime la correction et enregistre le HTML de la note, d'où la marque a été retirée.
     *
     * @throws TransactionEchoueeException
     */
    public function supprimer(GroupeNote $note, GroupeNoteCorrection $correction, string $noteHtml): void
    {
        try {
            DB::transaction(function () use ($note, $correction, $noteHtml) {
                $note->update(['contenu' => $noteHtml]);
                $correction->delete();
            });
        } catch (QueryException $e) {
            report($e);

            throw TransactionEchoueeException::depuis($e);
        }
    }
}
