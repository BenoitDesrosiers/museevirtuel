<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEnseignantRequest extends FormRequest
{
    /**
     * Détermine si l'utilisateur est autorisé à faire cette requête.
     * La vérification de rôle admin est assurée par le middleware de route.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Retourne les règles de validation pour la création d'un enseignant.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'prenom' => ['required', 'string', 'max:255'],
            'nom' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'etablissement_id' => ['nullable', 'integer', 'exists:etablissements,id'],
        ];
    }
}
