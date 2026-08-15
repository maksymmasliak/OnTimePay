<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isChangingOwnPassword = $this->filled('password')
            && $this->user()
            && $this->route('user') instanceof User
            && $this->user()->id === $this->route('user')->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes', 'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($this->route('user')),
            ],
            'role' => ['sometimes', Rule::in(['owner', 'manager'])],
            'password' => ['sometimes', 'required', 'string', 'min:8', 'confirmed'],
            'current_password' => [Rule::requiredIf($isChangingOwnPassword), 'string'],
        ];
    }
}
