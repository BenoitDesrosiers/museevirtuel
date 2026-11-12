<?php

namespace App\Http\Requests\Classe;

use App\Enums\StatutEtudiantCours;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valide la mise à jour d'un étudiant inscrit dans une section.
 */
class UpdateClasseEtudiantRequest extends FormRequest
{
    /**
     * La classe doit appartenir au cours, que l'utilisateur doit pouvoir modifier,
     * et l'étudiant doit être inscrit dans la classe.
     */
    public function authorize(): bool
    {
        $cours = $this->route('cours');
        $classe = $this->route('classe');
        abort_if($classe->cours_id !== $cours->id, 404);

        if ($this->user()->cannot('update', $cours)) {
            return false;
        }

        abort_unless($classe->etudiants()->whereKey($this->route('etudiant')->id)->exists(), 404);

        return true;
    }

    /**
     * Retourne les règles de validation pour la mise à jour d'un étudiant.
     * L'unicité du courriel ignore l'étudiant de la route.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'prenom' => ['required', 'string', 'max:255'],
            'nom' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)
                ->ignore($this->route('etudiant')->id)],
            'no_da' => ['required', 'integer'],
            'statut_cours' => ['required', Rule::enum(StatutEtudiantCours::class)],
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
            'prenom.required' => __('classe_etudiant.prenom.required'),
            'prenom.string' => __('classe_etudiant.prenom.string'),
            'prenom.max' => __('classe_etudiant.prenom.max'),
            'nom.required' => __('classe_etudiant.nom.required'),
            'nom.string' => __('classe_etudiant.nom.string'),
            'nom.max' => __('classe_etudiant.nom.max'),
            'email.required' => __('classe_etudiant.email.required'),
            'email.string' => __('classe_etudiant.email.string'),
            'email.email' => __('classe_etudiant.email.email'),
            'email.max' => __('classe_etudiant.email.max'),
            'email.unique' => __('classe_etudiant.email.unique'),
            'no_da.required' => __('classe_etudiant.no_da.required'),
            'no_da.integer' => __('classe_etudiant.no_da.integer'),
            'statut_cours.required' => __('classe_etudiant.statut_cours.required'),
            'statut_cours.enum' => __('classe_etudiant.statut_cours.enum'),
        ];
    }
}
