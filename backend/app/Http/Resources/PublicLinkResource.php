<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicLinkResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'type' => $this->type,
            'id' => $this->item->uuid,
            'name' => $this->type === 'file' ? $this->item->original_name : $this->item->name,
            'enabled' => $this->link?->enabled ?? false,
            'permission' => $this->link?->permission->value ?? 'viewer',
            'url' => $this->url,
            'createdAt' => $this->link?->created_at?->toISOString(),
            'updatedAt' => $this->link?->updated_at?->toISOString(),
        ];
    }
}
