<?php

namespace App\Http\Requests;

use App\Enums\SharePermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShareRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['permission' => ['required', Rule::enum(SharePermission::class)]];
    }
}
