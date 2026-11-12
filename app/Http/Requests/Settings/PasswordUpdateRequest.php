<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PasswordUpdateRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'current_password' => $this->currentPasswordRules(),
            'password' => $this->passwordRules(),
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
            'current_password.required' => __('password.current_password.required'),
            'current_password.string' => __('password.current_password.string'),
            'current_password.current_password' => __('password.current_password.current_password'),
            'password.required' => __('password.password.required'),
            'password.string' => __('password.password.string'),
            'password.min' => __('password.password.min'),
            'password.confirmed' => __('password.password.confirmed'),
        ];
    }
}
