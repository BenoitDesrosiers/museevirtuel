<?php

namespace App\Http\Requests\Groupe;

use App\Models\ProjetRecherche;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Valide l'assignation (ou la désassignation) d'une tâche à un membre du groupe.
 */
class AssignerGroupeTacheRequest extends FormRequest
{
    /**
     * Seuls les membres du groupe peuvent assigner une tâche, dans une chaîne d'URL cohérente.
     */
    public function authorize(): bool
    {
        $cours = $this->route('cours');
        $classe = $this->route('classe');
        $groupe = $this->route('groupe');
        $typeProjet = $this->route('typeProjet');

        abort_if($classe->cours_id !== $cours->id, 404);
        abort_if($groupe->classe_id !== $classe->id, 404);
        abort_if($typeProjet->cours_id !== $cours->id, 404);
        abort_if($this->route('tache')->type_projet_id !== $typeProjet->id, 404);

        return $this->user()->can('contribuer', [ProjetRecherche::class, $groupe]);
    }

    /**
     * Retourne les règles de validation de l'utilisateur assigné.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'assigne_a' => ['nullable', 'integer', 'exists:users,id'],
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
            'assigne_a.integer' => __('groupe_tache.assigne_a.integer'),
            'assigne_a.exists' => __('groupe_tache.assigne_a.exists'),
        ];
    }
}
