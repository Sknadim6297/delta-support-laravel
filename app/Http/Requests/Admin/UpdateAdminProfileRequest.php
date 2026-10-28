<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    public function rules(): array
    {
        $emailChanged = $this->string('email')->toString() !== (string) $this->user()?->email;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->user()?->id),
            ],
            'email_password' => [$emailChanged ? 'required' : 'nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Admin Name is required.',
            'email.required' => 'Email Address is required.',
            'email.email' => 'Enter a valid email address.',
            'email.unique' => 'That email address is already in use.',
            'email_password.required' => 'Enter your current password to change the email address.',
        ];
    }
}
