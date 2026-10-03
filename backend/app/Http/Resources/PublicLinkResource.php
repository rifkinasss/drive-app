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
            'linkId' => $this->link?->uuid,
            'name' => $this->type === 'file' ? $this->item->original_name : $this->item->name,
            'enabled' => $this->link?->enabled ?? false,
            'permission' => $this->link?->permission->value ?? 'viewer',
            'passwordProtected' => filled($this->link?->password_hash),
            'allowDownload' => $this->link?->allow_download ?? true,
            'expiresAt' => $this->link?->expires_at?->toISOString(),
            'url' => $this->url,
            'createdAt' => $this->link?->created_at?->toISOString(),
            'updatedAt' => $this->link?->updated_at?->toISOString(),
            'views' => (int) ($this->link?->view_count ?? 0),
            'downloads' => (int) ($this->link?->download_count ?? 0),
            'lastAccessedAt' => $this->link?->last_accessed_at?->toISOString(),
            'status' => ! $this->link?->enabled ? 'revoked' : ($this->link?->expires_at?->isPast() ? 'expired' : 'active'),
        ];
    }
}
