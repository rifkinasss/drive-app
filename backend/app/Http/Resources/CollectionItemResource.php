<?php

namespace App\Http\Resources;

use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CollectionItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isFile = $this->resource instanceof File;
        $location = $isFile ? $this->folder : $this->parent;

        return [
            'type' => $isFile ? 'file' : 'folder',
            'id' => $this->uuid,
            'name' => $isFile ? $this->original_name : $this->name,
            'extension' => $isFile ? $this->extension : null,
            'mimeType' => $isFile ? $this->mime_type : null,
            'sizeBytes' => $isFile ? $this->size_bytes : null,
            'starred' => (bool) $this->is_starred,
            'folderId' => $isFile ? $this->folder?->uuid : $this->parent?->uuid,
            'location' => $location ? [
                'folderId' => $location->uuid,
                'folderName' => $location->name,
            ] : null,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
