<?php

namespace App\Http\Resources;

use App\Models\InternalShare;
use App\Models\PublicShareLink;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FileDetailsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'type' => 'file',
            'name' => $this->original_name,
            'extension' => $this->extension,
            'mimeType' => $this->mime_type,
            'sizeBytes' => $this->size_bytes,
            'owner' => ['id' => $this->owner->getKey(), 'name' => $this->owner->name],
            'location' => $this->folder ? ['id' => $this->folder->uuid, 'name' => $this->folder->name] : null,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            'starred' => (bool) $this->is_starred,
            'trashedAt' => $this->trashed_at?->toISOString(),
            'shared' => InternalShare::query()->where('shareable_type', 'file')->where('shareable_id', $this->getKey())->exists(),
            'publicLinkEnabled' => PublicShareLink::query()->where('shareable_type', 'file')->where('shareable_id', $this->getKey())->where('enabled', true)->exists(),
        ];
    }
}
