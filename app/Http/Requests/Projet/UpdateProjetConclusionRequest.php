<?php

namespace App\Http\Requests\Projet;

use App\Models\Classe;
use App\Models\Cours;
use App\Models\Groupe;
use App\Models\ProjetRecherche;
use App\Models\TypeProjet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Valide la sauvegarde de la conclusion individuelle d'un membre du groupe.
 */
class UpdateProjetConclusionRequest extends FormRequest
{
    /**
     * Vérifie la chaîne d'URL et l'accès au projet du groupe.
     *
     * @throws HttpException
     */
    public function authorize(): bool
    {
        /** @var Cours $cours */
        $cours = $this->route('cours');
        /** @var Classe $classe */
        $classe = $this->route('classe');
        /** @var Groupe $groupe */
        $groupe = $this->route('groupe');
        /** @var TypeProjet $typeProjet */
        $typeProjet = $this->route('typeProjet');

        abort_if($classe->cours_id !== $cours->id, 404);
        abort_if($groupe->classe_id !== $classe->id, 404);
        abort_if($typeProjet->cours_id !== $cours->id, 404);

        return $this->user()->can('acceder', [ProjetRecherche::class, $groupe]);
    }

    /**
     * Retourne les règles de validation de la conclusion individuelle.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var TypeProjet $typeProjet */
        $typeProjet = $this->route('typeProjet');

        /** @var Groupe $groupe */
        $groupe = $this->route('groupe');

        return [
            'contenu' => ['nullable', 'string'],
            // La conclusion ciblée doit appartenir à un membre du groupe (anti-IDOR)
            'user_id' => ['required', 'integer',
                Rule::exists('groupe_etudiant', 'user_id')->where('groupe_id', $groupe->id)],
            'section_id' => ['nullable', 'integer',
                Rule::exists('type_projet_sections', 'id')->where('type_projet_id', $typeProjet->id)],
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
            'contenu.string' => __('projet_conclusion.contenu.string'),
            'user_id.required' => __('projet_conclusion.user_id.required'),
            'user_id.integer' => __('projet_conclusion.user_id.integer'),
            'user_id.exists' => __('projet_conclusion.user_id.exists'),
            'section_id.integer' => __('projet_conclusion.section_id.integer'),
            'section_id.exists' => __('projet_conclusion.section_id.exists'),
        ];
    }
}
