<?php

namespace App\Http\Requests;

use App\Enums\SharePermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShareRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recipientId' => ['required', 'integer', 'exists:users,id'],
            'permission' => ['required', Rule::enum(SharePermission::class)],
        ];
    }
}
