<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjetQuestionChoisie extends Model
{
    protected $table = 'projet_questions_choisies';

    protected $fillable = [
        'projet_id',
        'section_id',
        'question_banque_id',
    ];

    /**
     * Retourne le projet de recherche associé à ce choix.
     */
    public function projet(): BelongsTo
    {
        return $this->belongsTo(ProjetRecherche::class, 'projet_id');
    }

    /**
     * Retourne la section à laquelle appartient ce choix.
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(TypeProjetSection::class, 'section_id');
    }

    /**
     * Retourne la question choisie dans la banque.
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(QuestionBanque::class, 'question_banque_id');
    }
}
