<?php

namespace App\Http\Requests\Invitation;

use App\Services\PasswordPolicyService;
use Illuminate\Foundation\Http\FormRequest;

class AcceptInvitationRequest extends FormRequest
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
