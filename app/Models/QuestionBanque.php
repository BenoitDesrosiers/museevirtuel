<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestionBanque extends Model
{
    protected $table = 'question_banques';

    protected $fillable = [
        'section_id',
        'contenu',
        'ordre',
    ];

    /**
     * Retourne la section de TypeProjet à laquelle appartient cette question.
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(TypeProjetSection::class, 'section_id');
    }

    /**
     * Retourne les choix de questions faits par les projets pour cette question.
     */
    public function choix(): HasMany
    {
        return $this->hasMany(ProjetQuestionChoisie::class, 'question_banque_id');
    }
}
