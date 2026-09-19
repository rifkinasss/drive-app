<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BrowserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'folderId' => ['nullable', 'uuid'],
            'sort' => ['nullable', Rule::in(['name', 'modified', 'created', 'size'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'search' => ['nullable', 'string', 'max:200'],
        ];
    }
}
