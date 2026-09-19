<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->original_name,
            'folderId' => $this->owner_id === $request->user()?->getKey() ? $this->folder?->uuid : null,
            'extension' => $this->extension,
            'mimeType' => $this->mime_type,
            'sizeBytes' => $this->size_bytes,
            'checksum' => $this->checksum,
            'starred' => $this->is_starred,
            'trashedAt' => $this->trashed_at?->toISOString(),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
