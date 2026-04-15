<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClasseRequest extends FormRequest
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
     * Retourne les règles de validation pour la création d'une classe.
     * Le code est optionnel : s'il n'est pas fourni, il sera auto-généré par l'Observer.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:20'],
        ];
    }
}
