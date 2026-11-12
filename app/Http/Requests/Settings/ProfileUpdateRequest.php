<?php

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->profileRules($this->user()->id);
    }

    /**
     * Retourne les messages d'erreur de validation personnalisés.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'prenom.required' => __('profile.prenom.required'),
            'prenom.string' => __('profile.prenom.string'),
            'prenom.max' => __('profile.prenom.max'),
            'nom.required' => __('profile.nom.required'),
            'nom.string' => __('profile.nom.string'),
            'nom.max' => __('profile.nom.max'),
            'email.required' => __('profile.email.required'),
            'email.string' => __('profile.email.string'),
            'email.email' => __('profile.email.email'),
            'email.max' => __('profile.email.max'),
            'email.unique' => __('profile.email.unique'),
        ];
    }
}
