<?php

namespace App\Http\Requests;

use App\Enums\ActivityAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'cursor' => ['nullable', 'string', 'max:255'],
            'action' => ['nullable', 'array'],
            'action.*' => [Rule::enum(ActivityAction::class)],
            'type' => ['nullable', Rule::in(['file', 'folder', 'system'])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:200'],
        ];
    }
}
