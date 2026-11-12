<?php

namespace App\Http\Requests\Projet;

use App\Models\ProjetRecherche;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Valide l'enregistrement du consentement vidéo d'un utilisateur pour un projet.
 */
class StoreConsentementVideoRequest extends FormRequest
{
    /**
     * Seuls les membres du groupe et la personne âgée liée peuvent signer le consentement.
     */
    public function authorize(): bool
    {
        $cours = $this->route('cours');
        $groupe = $this->route('groupe');

        abort_if($groupe->classe->cours_id !== $cours->id, 404);
        abort_if($this->route('typeProjet')->cours_id !== $cours->id, 404);

        return $this->user()->can('consentir', [ProjetRecherche::class, $groupe]);
    }

    /**
     * Retourne les règles de validation du consentement vidéo.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'accepte' => ['required', 'boolean'],
            'signature' => ['nullable', 'string', 'max:65535'],
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
            'accepte.required' => __('consentement_video.accepte.required'),
            'accepte.boolean' => __('consentement_video.accepte.boolean'),
            'signature.string' => __('consentement_video.signature.string'),
            'signature.max' => __('consentement_video.signature.max'),
        ];
    }
}
