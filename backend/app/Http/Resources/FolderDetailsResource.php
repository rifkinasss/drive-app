<?php

namespace App\Http\Resources;

use App\Models\InternalShare;
use App\Models\PublicShareLink;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FolderDetailsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'type' => 'folder',
            'name' => $this->name,
            'extension' => null,
            'mimeType' => 'inode/directory',
            'sizeBytes' => null,
            'owner' => ['id' => $this->owner->getKey(), 'name' => $this->owner->name],
            'location' => $this->parent ? ['id' => $this->parent->uuid, 'name' => $this->parent->name] : null,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            'starred' => (bool) $this->is_starred,
            'trashedAt' => $this->trashed_at?->toISOString(),
            'shared' => InternalShare::query()->where('shareable_type', 'folder')->where('shareable_id', $this->getKey())->exists(),
            'publicLinkEnabled' => PublicShareLink::query()->where('shareable_type', 'folder')->where('shareable_id', $this->getKey())->where('enabled', true)->exists(),
        ];
    }
}
