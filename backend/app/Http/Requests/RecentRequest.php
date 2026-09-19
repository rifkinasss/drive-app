<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['limit' => ['nullable', 'integer', 'min:1', 'max:100']];
    }
}
