<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicSharedItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isFile = $this->type === 'file';

        return [
            'type' => $this->type,
            'name' => $isFile ? $this->item->original_name : $this->item->name,
            'mimeType' => $isFile ? $this->item->mime_type : null,
            'sizeBytes' => $isFile ? $this->item->size_bytes : null,
            'modifiedAt' => $this->item->updated_at?->utc()->toISOString(),
            'ownerDisplayName' => $this->owner->name,
            'permission' => 'viewer',
        ];
    }
}
