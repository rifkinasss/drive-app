<?php

namespace App\Http\Requests\File;

use App\Services\SystemSettingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:'.(int) ceil(app(SystemSettingService::class)->getInt('storage.max_upload_size_bytes') / 1024)],
            'folderId' => ['nullable', 'uuid'],
            'conflictStrategy' => ['nullable', Rule::in(['ask', 'keep_both', 'replace'])],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $file = $this->file('file');
            if ($file !== null && $file->getSize() > app(SystemSettingService::class)->getInt('storage.max_upload_size_bytes')) {
                $validator->errors()->add('file', 'The uploaded file is too large.');
            }
            $extension = $file === null ? null : mb_strtolower((string) pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
            if ($extension !== null && $extension !== '' && in_array($extension, app(SystemSettingService::class)->getJson('storage.blocked_extensions'), true)) {
                $validator->errors()->add('file', 'This file type is not allowed.');
            }
        });
    }
}
