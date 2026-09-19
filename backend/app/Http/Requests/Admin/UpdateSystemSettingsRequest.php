<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSystemSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['settings' => ['required', 'array']];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['settings' => $this->input('settings') ?? $this->except(['_token'])]);
    }
}
