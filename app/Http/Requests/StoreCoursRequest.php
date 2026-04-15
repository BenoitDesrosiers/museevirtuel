<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'nom_cours' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'code' => ['required', 'string', 'max:20'],
            'groupe' => ['required', 'string', 'max:20'],
        ];
    }
}
