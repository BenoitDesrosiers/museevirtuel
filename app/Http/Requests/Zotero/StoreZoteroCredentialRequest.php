<?php

namespace App\Http\Requests\Zotero;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation des identifiants Zotero de l'étudiant (clé API + identifiant utilisateur).
 */
class StoreZoteroCredentialRequest extends FormRequest
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
     * Retourne les règles de validation pour les identifiants Zotero.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'zotero_user_id' => ['required', 'string', 'max:50', 'regex:/^\d+$/'],
            'api_key' => ['required', 'string', 'max:255'],
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
            'zotero_user_id.required' => __('zotero_credential.zotero_user_id.required'),
            'zotero_user_id.string' => __('zotero_credential.zotero_user_id.string'),
            'zotero_user_id.max' => __('zotero_credential.zotero_user_id.max'),
            'zotero_user_id.regex' => __('zotero_credential.zotero_user_id.regex'),
            'api_key.required' => __('zotero_credential.api_key.required'),
            'api_key.string' => __('zotero_credential.api_key.string'),
            'api_key.max' => __('zotero_credential.api_key.max'),
        ];
    }
}
