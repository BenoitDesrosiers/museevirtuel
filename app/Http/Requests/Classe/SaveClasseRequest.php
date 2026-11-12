<?php

namespace App\Http\Requests\Classe;

use App\Models\Classe;
use App\Models\Cours;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valide la création et la mise à jour d'une classe (section) d'un cours.
 */
class SaveClasseRequest extends FormRequest
{
    /**
     * Création : l'utilisateur doit pouvoir modifier le cours.
     * Modification : la classe doit appartenir au cours et l'utilisateur doit pouvoir la modifier.
     */
    public function authorize(): bool
    {
        $classe = $this->route('classe');

        if ($classe === null) {
            return $this->user()->can('update', $this->route('cours'));
        }

        abort_if($classe->cours_id !== $this->route('cours')->id, 404);

        return $this->user()->can('update', $classe);
    }

    /**
     * Retourne les règles de validation d'une classe.
     * Le numéro doit être unique parmi les classes du cours, en ignorant la classe modifiée en mise à jour.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Cours $cours */
        $cours = $this->route('cours');

        /** @var Classe|null $classe */
        $classe = $this->route('classe');

        return [
            'numero' => ['required', 'digits:5',
                Rule::unique('classes')->where(fn ($q) => $q->where('cours_id', $cours->id))->ignore($classe?->id),
            ],
            'nom' => ['nullable', 'string', 'max:100'],
            'jour_semaine' => ['nullable', 'string', 'max:20'],
            'plage_horaire' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * Retourne les messages d'erreur de validation personnalisés pour une classe.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'numero.required' => __('classe.numero.required'),
            'numero.digits' => __('classe.numero.digits'),
            'numero.unique' => __('classe.numero.unique'),
            'nom.string' => __('classe.nom.string'),
            'nom.max' => __('classe.nom.max'),
            'jour_semaine.string' => __('classe.jour_semaine.string'),
            'jour_semaine.max' => __('classe.jour_semaine.max'),
            'plage_horaire.string' => __('classe.plage_horaire.string'),
            'plage_horaire.max' => __('classe.plage_horaire.max'),
        ];
    }
}
