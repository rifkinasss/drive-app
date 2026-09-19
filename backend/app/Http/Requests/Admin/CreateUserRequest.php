<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Services\PasswordPolicyService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class CreateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', new Enum(UserRole::class)],
            'password' => ['required', 'confirmed', app(PasswordPolicyService::class)->rule()],
        ];
    }
}
