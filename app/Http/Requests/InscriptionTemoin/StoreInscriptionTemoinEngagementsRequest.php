<?php

namespace App\Http\Requests\InscriptionTemoin;

use App\Http\Controllers\Temoin\InscriptionTemoinController;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation de l'étape 2 de l'inscription d'un témoin (engagements et signature).
 */
class StoreInscriptionTemoinEngagementsRequest extends FormRequest
{
    /**
     * Détermine si l'utilisateur est autorisé à faire cette requête.
     * L'autorisation reste vérifiée dans le contrôleur.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Retourne les règles de validation pour les engagements et la signature.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'engagements' => ['required', 'array', 'size:'.InscriptionTemoinController::NB_ENGAGEMENTS],
            'engagements.*' => ['accepted'],
            'signature' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }

    /**
     * Retourne les messages d'erreur de validation personnalisés.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'engagements.required' => __('inscription_temoin_engagements.engagements.required'),
            'engagements.array' => __('inscription_temoin_engagements.engagements.array'),
            'engagements.size' => __('inscription_temoin_engagements.engagements.size'),
            'engagements.*.accepted' => __('inscription_temoin_engagements.engagements.accepted'),
            'signature.required' => __('inscription_temoin_engagements.signature.required'),
            'signature.string' => __('inscription_temoin_engagements.signature.string'),
            'signature.min' => __('inscription_temoin_engagements.signature.min'),
            'signature.max' => __('inscription_temoin_engagements.signature.max'),
        ];
    }
}
