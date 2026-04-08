<?php

namespace App\Models;

use Database\Factories\ThematiqueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Thematique extends Model
{
    /** @use HasFactory<ThematiqueFactory> */
    use HasFactory;

    protected $fillable = [
        'nom',
        'description',
        'periode_historique',
        'enseignant_id',
    ];

    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enseignant_id');
    }
}
