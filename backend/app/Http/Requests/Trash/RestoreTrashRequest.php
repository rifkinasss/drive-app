<?php

namespace App\Http\Requests\Trash;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RestoreTrashRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['conflictStrategy' => ['nullable', Rule::in(['cancel', 'keep_both'])]];
    }
}
