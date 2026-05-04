<?php

namespace App\Http\Requests;

use App\Enums\TypeCours;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCoursRequest extends FormRequest
{
    /**
     * Détermine si l'utilisateur est autorisé à faire cette requête.
     * La vérification de rôle est déjà assurée par le middleware de route.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Retourne les règles de validation pour la création d'un cours.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'nom_cours' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'code' => ['required', 'string', 'max:20'],
            'groupe' => ['required', 'string', 'max:20'],
            'type_cours' => ['nullable', Rule::enum(TypeCours::class)],
            'taille_equipe_min' => ['nullable', 'integer', 'min:1', 'max:20'],
            'taille_equipe_max' => ['nullable', 'integer', 'min:1', 'max:20', 'gte:taille_equipe_min'],
        ];
    }
}
