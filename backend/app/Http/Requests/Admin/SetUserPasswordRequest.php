<?php

namespace App\Http\Requests\Admin;

use App\Services\PasswordPolicyService;
use Illuminate\Foundation\Http\FormRequest;

class SetUserPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'confirmed', app(PasswordPolicyService::class)->rule()],
        ];
    }
}
