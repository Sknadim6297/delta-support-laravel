<?php

namespace App\Http\Requests\Admin;

use App\Rules\StrongPassword;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAdminPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', new StrongPassword],
            'password_confirmation' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'Current Password is required.',
            'password.required' => 'New Password is required.',
            'password.min' => StrongPassword::MESSAGE,
            'password.confirmed' => 'New Password and Confirm New Password do not match.',
            'password_confirmation.required' => 'Confirm New Password is required.',
        ];
    }
}
