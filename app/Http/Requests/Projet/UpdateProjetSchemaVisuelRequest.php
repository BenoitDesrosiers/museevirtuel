<?php

namespace App\Http\Requests\Projet;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valide la sauvegarde du schéma visuel d'une section de projet.
 */
class UpdateProjetSchemaVisuelRequest extends FormRequest
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
     * Retourne les règles de validation du schéma visuel.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'contenu' => ['required', 'array'],
            'contenu.image_centrale' => ['nullable', 'string'],
            'contenu.zones' => ['required', 'array'],
            'contenu.zones.causes' => ['present', 'array'],
            'contenu.zones.activites' => ['present', 'array'],
            'contenu.zones.consequences' => ['present', 'array'],
            'contenu.zones.causes.*.id' => ['required', 'string'],
            'contenu.zones.causes.*.texte' => ['nullable', 'string', 'max:500'],
            'contenu.zones.causes.*.image' => ['nullable', 'string'],
            'contenu.zones.activites.*.id' => ['required', 'string'],
            'contenu.zones.activites.*.texte' => ['nullable', 'string', 'max:500'],
            'contenu.zones.activites.*.image' => ['nullable', 'string'],
            'contenu.zones.consequences.*.id' => ['required', 'string'],
            'contenu.zones.consequences.*.texte' => ['nullable', 'string', 'max:500'],
            'contenu.zones.consequences.*.image' => ['nullable', 'string'],
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
            'contenu.required' => __('projet_schema_visuel.contenu.required'),
            'contenu.array' => __('projet_schema_visuel.contenu.array'),
            'contenu.image_centrale.string' => __('projet_schema_visuel.contenu.image_centrale.string'),
            'contenu.zones.required' => __('projet_schema_visuel.contenu.zones.required'),
            'contenu.zones.array' => __('projet_schema_visuel.contenu.zones.array'),
            'contenu.zones.causes.array' => __('projet_schema_visuel.contenu.zones.causes.array'),
            'contenu.zones.activites.array' => __('projet_schema_visuel.contenu.zones.activites.array'),
            'contenu.zones.consequences.array' => __('projet_schema_visuel.contenu.zones.consequences.array'),
            'contenu.zones.causes.*.id.required' => __('projet_schema_visuel.contenu.zones.causes.id.required'),
            'contenu.zones.causes.*.id.string' => __('projet_schema_visuel.contenu.zones.causes.id.string'),
            'contenu.zones.causes.*.texte.string' => __('projet_schema_visuel.contenu.zones.causes.texte.string'),
            'contenu.zones.causes.*.texte.max' => __('projet_schema_visuel.contenu.zones.causes.texte.max'),
            'contenu.zones.causes.*.image.string' => __('projet_schema_visuel.contenu.zones.causes.image.string'),
            'contenu.zones.activites.*.id.required' => __('projet_schema_visuel.contenu.zones.activites.id.required'),
            'contenu.zones.activites.*.id.string' => __('projet_schema_visuel.contenu.zones.activites.id.string'),
            'contenu.zones.activites.*.texte.string' => __('projet_schema_visuel.contenu.zones.activites.texte.string'),
            'contenu.zones.activites.*.texte.max' => __('projet_schema_visuel.contenu.zones.activites.texte.max'),
            'contenu.zones.activites.*.image.string' => __('projet_schema_visuel.contenu.zones.activites.image.string'),
            'contenu.zones.consequences.*.id.required' => __('projet_schema_visuel.contenu.zones.consequences.id.required'),
            'contenu.zones.consequences.*.id.string' => __('projet_schema_visuel.contenu.zones.consequences.id.string'),
            'contenu.zones.consequences.*.texte.string' => __('projet_schema_visuel.contenu.zones.consequences.texte.string'),
            'contenu.zones.consequences.*.texte.max' => __('projet_schema_visuel.contenu.zones.consequences.texte.max'),
            'contenu.zones.consequences.*.image.string' => __('projet_schema_visuel.contenu.zones.consequences.image.string'),
        ];
    }
}
